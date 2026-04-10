<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\WhatsAppMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
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

                foreach (($value['messages'] ?? []) as $messagePayload) {
                    $messageId = $messagePayload['id'] ?? null;

                    if (! $messageId) {
                        continue;
                    }

                    $fromPhone = $this->normalizePhoneDigits((string) ($messagePayload['from'] ?? ''));
                    $recipientWaId = $this->normalizePhoneDigits((string) ($messagePayload['from'] ?? ''));
                    $timestamp = isset($messagePayload['timestamp'])
                        ? CarbonImmutable::createFromTimestamp((int) $messagePayload['timestamp'])
                        : now();

                    $attributes = [
                        'from_phone' => $fromPhone,
                        'recipient_wa_id' => $recipientWaId,
                        'conversation_key' => $this->resolveConversationKey($recipientWaId, $fromPhone),
                        'persona_id' => $this->findPersonaByPhone($fromPhone)?->id,
                        'body' => $this->extractInboundBody($messagePayload),
                        'direction' => 'inbound',
                        'message_type' => (string) ($messagePayload['type'] ?? 'unknown'),
                        'status' => 'received',
                        'reply_to_provider_message_id' => $messagePayload['context']['id'] ?? null,
                        'webhook_payload' => $messagePayload,
                        'read_in_app_at' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    WhatsAppMessage::query()->updateOrCreate(
                        ['provider_message_id' => $messageId],
                        $attributes,
                    );

                    $updated++;
                }

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
                        'conversation_key' => $this->resolveConversationKey(
                            (string) ($statusPayload['recipient_id'] ?? ''),
                            null,
                        ),
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
            'conversation_key' => $this->resolveConversationKey(null, $to),
            'body' => $body,
            'direction' => 'outbound',
            'message_type' => (string) ($payload['type'] ?? 'text'),
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
                'conversation_key' => $this->resolveConversationKey(
                    (string) ($responsePayload['contacts'][0]['wa_id'] ?? ''),
                    $to,
                ),
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

    protected function extractInboundBody(array $messagePayload): ?string
    {
        return match ((string) ($messagePayload['type'] ?? '')) {
            'text' => $messagePayload['text']['body'] ?? null,
            'button' => $messagePayload['button']['text'] ?? null,
            'interactive' => $messagePayload['interactive']['button_reply']['title']
                ?? $messagePayload['interactive']['list_reply']['title']
                ?? null,
            default => '['.((string) ($messagePayload['type'] ?? 'mensaje')).']',
        };
    }

    protected function resolveConversationKey(?string $recipientWaId, ?string $fallbackPhone): ?string
    {
        $waId = $this->normalizePhoneDigits((string) $recipientWaId);

        if ($waId !== null) {
            return $waId;
        }

        $phone = $this->normalizePhoneDigits((string) $fallbackPhone);

        if ($phone === null) {
            return null;
        }

        return str_starts_with($phone, '54') && ! str_starts_with($phone, '549')
            ? '549'.substr($phone, 2)
            : $phone;
    }

    protected function normalizePhoneDigits(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    protected function findPersonaByPhone(?string $phone): ?Persona
    {
        if ($phone === null) {
            return null;
        }

        /** @var Collection<int, Persona> $matches */
        $matches = Persona::query()
            ->whereIn('telefono_normalizado', $this->phoneCandidates($phone))
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return list<string>
     */
    protected function phoneCandidates(string $digits): array
    {
        $candidates = collect([$digits]);

        if (str_starts_with($digits, '549')) {
            $candidates->push('54'.substr($digits, 3));
            $candidates->push(substr($digits, 3));
        } elseif (str_starts_with($digits, '54')) {
            $local = substr($digits, 2);
            $candidates->push($local);
            $candidates->push('549'.$local);
        } else {
            $candidates->push('54'.$digits);
            $candidates->push('549'.$digits);
        }

        return $candidates
            ->filter(fn (?string $value): bool => filled($value))
            ->unique()
            ->values()
            ->all();
    }
}
