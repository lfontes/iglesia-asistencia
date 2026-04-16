<?php

namespace App\Jobs;

use App\Models\EventoFecha;
use App\Models\WhatsAppBulkDispatch;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;

class SendEventoReminderBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $eventoFechaId,
        public ?int $userId = null,
    ) {
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $eventoFecha = EventoFecha::query()
            ->with(['evento', 'inscripciones.persona'])
            ->find($this->eventoFechaId);

        if (! $eventoFecha) {
            return;
        }

        $enviadosHoy = WhatsAppMessage::query()
            ->where('use_case', 'recordatorio_evento')
            ->where('evento_fecha_id', $eventoFecha->id)
            ->whereDate('created_at', today())
            ->pluck('persona_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $enviados = 0;
        $omitidos = 0;
        $fallidos = 0;
        $detalles = [];

        foreach ($eventoFecha->inscripciones->where('estado', 'inscripto') as $inscripcion) {
            $persona = $inscripcion->persona;

            if (! $persona) {
                $omitidos++;
                $detalles[] = 'Inscripción sin persona asociada.';
                continue;
            }

            if (blank($persona->telefono_normalizado ?: $persona->telefono)) {
                $omitidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': sin teléfono válido.';
                continue;
            }

            if ($enviadosHoy->contains((int) $persona->id)) {
                $omitidos++;
                continue;
            }

            try {
                $whatsAppService->sendEventReminder($eventoFecha, $persona);
                $enviados++;
            } catch (RequestException $exception) {
                $fallidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': '
                    . (string) ($exception->response?->json('error.message') ?? $exception->getMessage());
            } catch (\RuntimeException $exception) {
                $omitidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': ' . $exception->getMessage();
            } catch (\Throwable $exception) {
                report($exception);
                $fallidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': ' . $exception->getMessage();
            }
        }

        WhatsAppBulkDispatch::query()->create([
            'use_case' => 'recordatorio_evento',
            'fecha_referencia' => $eventoFecha->fecha,
            'period_hash' => hash('sha256', 'evento_fecha:' . $eventoFecha->id . '|fecha:' . $eventoFecha->fecha),
            'period_summary' => trim(($eventoFecha->evento?->nombre ?? 'Evento') . ' - ' . $eventoFecha->fecha),
            'user_id' => $this->userId,
            'sent_count' => $enviados,
            'skipped_count' => $omitidos,
            'failed_count' => $fallidos,
            'meta' => [
                'evento_fecha_id' => $eventoFecha->id,
                'evento_nombre' => $eventoFecha->evento?->nombre,
                'detalles' => $detalles,
            ],
        ]);
    }
}
