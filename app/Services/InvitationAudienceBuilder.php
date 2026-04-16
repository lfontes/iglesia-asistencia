<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\EventoFecha;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;

class InvitationAudienceBuilder
{
    /**
     * @param  array{
     *   evento_fecha_ids_origen?:array<int|string>,
     *   grupo_ids_origen?:array<int|string>,
     *   evento_fecha_id_destino?:int|string|null,
     *   excluir_sin_telefono?:bool,
     *   excluir_ya_asistieron_destino?:bool,
     *   excluir_ya_invitados_destino?:bool
     * }  $filters
     * @return array{
     *   rows:Collection<int, array{
     *      persona_id:int,
     *      nombre:string,
     *      telefono:?string,
     *      telefono_normalizado:?string,
     *      sources:array<int, string>,
     *      has_phone:bool,
     *      already_attended_destination:bool,
     *      already_invited_destination:bool,
     *      eligible:bool
     *   }>,
     *   deliverable_people:Collection<int, \App\Models\Persona>,
     *   stats:array{
     *      total_unicos:int,
     *      sin_telefono:int,
     *      ya_asistieron_destino:int,
     *      ya_invitados:int,
     *      finales:int
     *   }
     * }
     */
    public function build(array $filters): array
    {
        $eventoFechaIdsOrigen = collect($filters['evento_fecha_ids_origen'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $grupoIdsOrigen = collect($filters['grupo_ids_origen'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $eventoFechaIdDestino = isset($filters['evento_fecha_id_destino']) && filled($filters['evento_fecha_id_destino'])
            ? (int) $filters['evento_fecha_id_destino']
            : null;

        $rows = $this->mergeSources(
            $this->fromEventAttendances($eventoFechaIdsOrigen),
            $this->fromGroupMemberships($grupoIdsOrigen),
        );

        if ($rows->isEmpty()) {
            return [
                'rows' => collect(),
                'deliverable_people' => collect(),
                'stats' => [
                    'total_unicos' => 0,
                    'sin_telefono' => 0,
                    'ya_asistieron_destino' => 0,
                    'ya_invitados' => 0,
                    'finales' => 0,
                ],
            ];
        }

        $alreadyAttendedIds = $eventoFechaIdDestino
            ? Asistencia::query()
                ->where('evento_fecha_id', $eventoFechaIdDestino)
                ->where('presente', true)
                ->whereIn('persona_id', $rows->pluck('persona_id'))
                ->pluck('persona_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
            : [];

        $alreadyInvitedIds = $eventoFechaIdDestino
            ? WhatsAppMessage::query()
                ->where('use_case', 'invitacion_evento')
                ->where('evento_fecha_id', $eventoFechaIdDestino)
                ->whereIn('persona_id', $rows->pluck('persona_id'))
                ->pluck('persona_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
            : [];

        $rows = $rows
            ->map(function (array $row) use ($filters, $alreadyAttendedIds, $alreadyInvitedIds): array {
                $alreadyAttended = in_array($row['persona_id'], $alreadyAttendedIds, true);
                $alreadyInvited = in_array($row['persona_id'], $alreadyInvitedIds, true);

                $eligible = true;

                if (($filters['excluir_sin_telefono'] ?? true) && ! $row['has_phone']) {
                    $eligible = false;
                }

                if (($filters['excluir_ya_asistieron_destino'] ?? true) && $alreadyAttended) {
                    $eligible = false;
                }

                if (($filters['excluir_ya_invitados_destino'] ?? true) && $alreadyInvited) {
                    $eligible = false;
                }

                $row['already_attended_destination'] = $alreadyAttended;
                $row['already_invited_destination'] = $alreadyInvited;
                $row['eligible'] = $eligible;

                return $row;
            })
            ->sortBy([
                ['eligible', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();

        $deliverableIds = $rows
            ->filter(fn (array $row): bool => $row['eligible'])
            ->pluck('persona_id');

        return [
            'rows' => $rows,
            'deliverable_people' => \App\Models\Persona::query()
                ->whereIn('id', $deliverableIds)
                ->get()
                ->keyBy('id')
                ->sortKeys()
                ->values(),
            'stats' => [
                'total_unicos' => $rows->count(),
                'sin_telefono' => $rows->where('has_phone', false)->count(),
                'ya_asistieron_destino' => $rows->where('already_attended_destination', true)->count(),
                'ya_invitados' => $rows->where('already_invited_destination', true)->count(),
                'finales' => $rows->where('eligible', true)->count(),
            ],
        ];
    }

    /**
     * @param  list<int>  $eventoFechaIds
     * @return Collection<int, array{
     *   persona_id:int,
     *   nombre:string,
     *   telefono:?string,
     *   telefono_normalizado:?string,
     *   sources:array<int, string>,
     *   has_phone:bool
     * }>
     */
    protected function fromEventAttendances(array $eventoFechaIds): Collection
    {
        if ($eventoFechaIds === []) {
            return collect();
        }

        return Asistencia::query()
            ->with(['persona:id,nombre,apellido,telefono,telefono_normalizado', 'eventoFecha.evento:id,nombre'])
            ->whereIn('evento_fecha_id', $eventoFechaIds)
            ->where('presente', true)
            ->get()
            ->map(function (Asistencia $asistencia): array {
                $persona = $asistencia->persona;
                $eventoFecha = $asistencia->eventoFecha;
                $source = trim(
                    'Evento: '
                    . ($eventoFecha?->evento?->nombre ?? 'Sin nombre')
                    . ' ('
                    . ($eventoFecha?->fecha ?? 'sin fecha')
                    . ')'
                );

                return [
                    'persona_id' => (int) $persona->id,
                    'nombre' => trim(($persona->apellido ?? '') . ' ' . ($persona->nombre ?? '')),
                    'telefono' => $persona->telefono,
                    'telefono_normalizado' => $persona->telefono_normalizado,
                    'sources' => [$source],
                    'has_phone' => filled($persona->telefono_normalizado ?: $persona->telefono),
                ];
            });
    }

    /**
     * @param  list<int>  $grupoIds
     * @return Collection<int, array{
     *   persona_id:int,
     *   nombre:string,
     *   telefono:?string,
     *   telefono_normalizado:?string,
     *   sources:array<int, string>,
     *   has_phone:bool
     * }>
     */
    protected function fromGroupMemberships(array $grupoIds): Collection
    {
        if ($grupoIds === []) {
            return collect();
        }

        return ParticipacionGrupo::query()
            ->with(['persona:id,nombre,apellido,telefono,telefono_normalizado', 'grupo:id,nombre'])
            ->whereIn('grupo_id', $grupoIds)
            ->where(function ($query): void {
                $query->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', now()->toDateString());
            })
            ->get()
            ->map(function (ParticipacionGrupo $participacion): array {
                $persona = $participacion->persona;
                $source = trim('Grupo: ' . ($participacion->grupo?->nombre ?? 'Sin nombre'));

                return [
                    'persona_id' => (int) $persona->id,
                    'nombre' => trim(($persona->apellido ?? '') . ' ' . ($persona->nombre ?? '')),
                    'telefono' => $persona->telefono,
                    'telefono_normalizado' => $persona->telefono_normalizado,
                    'sources' => [$source],
                    'has_phone' => filled($persona->telefono_normalizado ?: $persona->telefono),
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  ...$sources
     * @return Collection<int, array{
     *   persona_id:int,
     *   nombre:string,
     *   telefono:?string,
     *   telefono_normalizado:?string,
     *   sources:array<int, string>,
     *   has_phone:bool
     * }>
     */
    protected function mergeSources(Collection ...$sources): Collection
    {
        return collect($sources)
            ->flatten(1)
            ->groupBy('persona_id')
            ->map(function (Collection $items): array {
                $first = $items->first();

                return [
                    'persona_id' => (int) $first['persona_id'],
                    'nombre' => (string) $first['nombre'],
                    'telefono' => $first['telefono'],
                    'telefono_normalizado' => $first['telefono_normalizado'],
                    'sources' => $items->pluck('sources')->flatten()->unique()->sort()->values()->all(),
                    'has_phone' => $items->contains(fn (array $item): bool => (bool) $item['has_phone']),
                ];
            })
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function getEventoFechaOptions(): array
    {
        return EventoFecha::query()
            ->with('evento:id,nombre')
            ->orderByDesc('fecha')
            ->get()
            ->mapWithKeys(fn (EventoFecha $eventoFecha): array => [
                $eventoFecha->id => trim(($eventoFecha->evento?->nombre ?? 'Evento') . ' - ' . $eventoFecha->fecha),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getGrupoOptions(): array
    {
        return Grupo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }
}
