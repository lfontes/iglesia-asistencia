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
        return $this->sendPayload(
            $to,
            $body,
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                'body' => $body,
                ],
            ],
            [],
        );
    }

    public function getTestRecipient(): ?string
    {
        $recipient = trim((string) config('services.whatsapp.test_recipient'));

        return $recipient !== '' ? $recipient : null;
    }

    /**
     * @param  list<string>  $bodyParameters
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function sendTemplate(string $to, string $templateKey, array $bodyParameters, ?string $renderedBody = null): array
    {
        $template = config("services.whatsapp.templates.{$templateKey}");

        if (! is_array($template)) {
            throw new RuntimeException("No existe configuración para la plantilla {$templateKey}.");
        }

        $templateName = (string) ($template['name'] ?? '');
        $language = (string) ($template['language'] ?? 'es');

        if ($templateName === '') {
            throw new RuntimeException("Falta el nombre de la plantilla {$templateKey}.");
        }

        return $this->sendPayload(
            $to,
            $renderedBody ?? implode(' | ', $bodyParameters),
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $language,
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => collect($bodyParameters)
                                ->map(fn (string $value): array => [
                                    'type' => 'text',
                                    'text' => $value,
                                ])
                                ->all(),
                        ],
                    ],
                ],
            ],
            [],
        );
    }

    /**
     * @param  list<string>  $bodyParameters
     * @param  array{persona_id?:int|null,grupo_id?:int|null,use_case?:string|null,periodo_inicio?:string|null,periodo_fin?:string|null}  $context
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function sendTemplateWithContext(
        string $to,
        string $templateKey,
        array $bodyParameters,
        array $context,
        ?string $renderedBody = null,
    ): array {
        $template = config("services.whatsapp.templates.{$templateKey}");

        if (! is_array($template)) {
            throw new RuntimeException("No existe configuración para la plantilla {$templateKey}.");
        }

        $templateName = (string) ($template['name'] ?? '');
        $language = (string) ($template['language'] ?? 'es');

        if ($templateName === '') {
            throw new RuntimeException("Falta el nombre de la plantilla {$templateKey}.");
        }

        return $this->sendPayload(
            $to,
            $renderedBody ?? implode(' | ', $bodyParameters),
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $language,
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => collect($bodyParameters)
                                ->map(fn (string $value): array => [
                                    'type' => 'text',
                                    'text' => $value,
                                ])
                                ->all(),
                        ],
                    ],
                ],
            ],
            $context,
        );
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{persona_id?:int|null,grupo_id?:int|null,use_case?:string|null,periodo_inicio?:string|null,periodo_fin?:string|null}  $context
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function sendPayload(string $to, string $body, array $payload, array $context): array
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
            'persona_id' => $context['persona_id'] ?? null,
            'grupo_id' => $context['grupo_id'] ?? null,
            'use_case' => $context['use_case'] ?? null,
            'periodo_inicio' => $context['periodo_inicio'] ?? null,
            'periodo_fin' => $context['periodo_fin'] ?? null,
            'status' => 'pending',
        ]);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post("https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages", $payload)
                ->throw();

            $responsePayload = $response->json();

            $message->update([
                'provider_message_id' => $responsePayload['messages'][0]['id'] ?? null,
                'recipient_wa_id' => $responsePayload['contacts'][0]['wa_id'] ?? null,
                'status' => 'accepted',
                'response_payload' => $responsePayload,
                'accepted_at' => now(),
            ]);

            return $responsePayload;
        } catch (RequestException $exception) {
            $responsePayload = $exception->response?->json();

            $message->update([
                'status' => 'failed_request',
                'error_message' => $responsePayload['error']['message'] ?? $exception->getMessage(),
                'response_payload' => $responsePayload,
                'failed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
