<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function sendText(string $to, string $body): array
    {
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $accessToken = (string) config('services.whatsapp.access_token');
        $graphVersion = (string) config('services.whatsapp.graph_version', 'v23.0');

        if ($phoneNumberId === '' || $accessToken === '') {
            throw new RuntimeException('Faltan credenciales de WhatsApp en la configuración.');
        }

        $message = WhatsAppMessage::query()->create([
            'to_phone' => $to,
            'body' => $body,
            'direction' => 'outbound',
            'status' => 'pending',
        ]);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post("https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $body,
                    ],
                ])
                ->throw();

            $payload = $response->json();

            $message->update([
                'provider_message_id' => $payload['messages'][0]['id'] ?? null,
                'recipient_wa_id' => $payload['contacts'][0]['wa_id'] ?? null,
                'status' => 'accepted',
                'response_payload' => $payload,
                'accepted_at' => now(),
            ]);

            return $payload;
        } catch (RequestException $exception) {
            $payload = $exception->response?->json();

            $message->update([
                'status' => 'failed_request',
                'error_message' => $payload['error']['message'] ?? $exception->getMessage(),
                'response_payload' => $payload,
                'failed_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function getTestRecipient(): ?string
    {
        $recipient = trim((string) config('services.whatsapp.test_recipient'));

        return $recipient !== '' ? $recipient : null;
    }

    public function handleWebhook(array $payload): int
    {
        $updated = 0;

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['statuses'] ?? []) as $statusPayload) {
                    $messageId = $statusPayload['id'] ?? null;

                    if (! $messageId) {
                        continue;
                    }

                    $status = (string) ($statusPayload['status'] ?? 'unknown');
                    $timestamp = isset($statusPayload['timestamp'])
                        ? CarbonImmutable::createFromTimestamp((int) $statusPayload['timestamp'])
                        : now();

                    $attributes = [
                        'recipient_wa_id' => $statusPayload['recipient_id'] ?? null,
                        'status' => $status,
                        'webhook_payload' => $statusPayload,
                        'error_message' => $this->extractErrorMessage($statusPayload),
                    ];

                    if ($status === 'sent') {
                        $attributes['sent_at'] = $timestamp;
                    }

                    if ($status === 'delivered') {
                        $attributes['delivered_at'] = $timestamp;
                    }

                    if ($status === 'read') {
                        $attributes['read_at'] = $timestamp;
                    }

                    if ($status === 'failed') {
                        $attributes['failed_at'] = $timestamp;
                    }

                    WhatsAppMessage::query()->updateOrCreate(
                        ['provider_message_id' => $messageId],
                        $attributes,
                    );

                    $updated++;
                }
            }
        }

        return $updated;
    }

    protected function extractErrorMessage(array $statusPayload): ?string
    {
        $errors = $statusPayload['errors'] ?? [];

        if (! is_array($errors) || $errors === []) {
            return null;
        }

        $first = $errors[0];

        return $first['message'] ?? $first['title'] ?? $first['error_data']['details'] ?? null;
    }
}
