<?php

namespace App\Jobs;

use App\Models\EventoFecha;
use App\Models\WhatsAppBulkDispatch;
use App\Services\InvitationAudienceBuilder;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;

class SendInvitationCampaignJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *   evento_fecha_ids_origen?:array<int|string>,
     *   grupo_ids_origen?:array<int|string>,
     *   evento_fecha_id_destino?:int|string|null,
     *   excluir_sin_telefono?:bool,
     *   excluir_ya_asistieron_destino?:bool,
     *   excluir_ya_invitados_destino?:bool
     * }  $filters
     */
    public function __construct(
        public array $filters,
        public ?int $userId = null,
    ) {
    }

    public function handle(InvitationAudienceBuilder $audienceBuilder, WhatsAppService $whatsAppService): void
    {
        $eventoFechaIdDestino = isset($this->filters['evento_fecha_id_destino']) && filled($this->filters['evento_fecha_id_destino'])
            ? (int) $this->filters['evento_fecha_id_destino']
            : null;

        if (! $eventoFechaIdDestino) {
            return;
        }

        $eventoFechaDestino = EventoFecha::query()
            ->with('evento')
            ->find($eventoFechaIdDestino);

        if (! $eventoFechaDestino) {
            return;
        }

        $preview = $audienceBuilder->build($this->filters);
        $detalles = [];
        $enviados = 0;
        $omitidos = 0;
        $fallidos = 0;

        foreach ($preview['rows'] as $row) {
            if (! $row['eligible']) {
                $omitidos++;
                continue;
            }

            $persona = $preview['deliverable_people']->firstWhere('id', $row['persona_id']);

            if (! $persona) {
                $omitidos++;
                $detalles[] = $row['nombre'] . ': no se encontró la persona al enviar.';
                continue;
            }

            try {
                $whatsAppService->sendEventInvitation($eventoFechaDestino, $persona);
                $enviados++;
            } catch (RequestException $exception) {
                $fallidos++;
                $detalles[] = $row['nombre'] . ': ' . (string) ($exception->response?->json('error.message') ?? $exception->getMessage());
            } catch (\RuntimeException $exception) {
                $omitidos++;
                $detalles[] = $row['nombre'] . ': ' . $exception->getMessage();
            } catch (\Throwable $exception) {
                report($exception);
                $fallidos++;
                $detalles[] = $row['nombre'] . ': ' . $exception->getMessage();
            }
        }

        $sourceEventIds = collect($this->filters['evento_fecha_ids_origen'] ?? [])->filter()->values()->all();
        $sourceGroupIds = collect($this->filters['grupo_ids_origen'] ?? [])->filter()->values()->all();

        WhatsAppBulkDispatch::query()->create([
            'use_case' => 'invitacion_evento',
            'fecha_referencia' => $eventoFechaDestino->fecha,
            'period_hash' => hash(
                'sha256',
                implode('|', [
                    'destino:' . $eventoFechaDestino->id,
                    'eventos:' . implode(',', $sourceEventIds),
                    'grupos:' . implode(',', $sourceGroupIds),
                ])
            ),
            'period_summary' => trim(($eventoFechaDestino->evento?->nombre ?? 'Evento') . ' - ' . $eventoFechaDestino->fecha),
            'user_id' => $this->userId,
            'sent_count' => $enviados,
            'skipped_count' => $omitidos,
            'failed_count' => $fallidos,
            'meta' => [
                'filters' => $this->filters,
                'stats' => $preview['stats'],
                'detalles' => $detalles,
            ],
        ]);
    }
}
