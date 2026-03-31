<?php

namespace App\Services;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AsistenciasPendientesService
{
    /**
     * @return Collection<int, array{
     *     grupo_id:int,
     *     grupo:string,
     *     frecuencia:string,
     *     periodo_inicio:string,
     *     periodo_fin:string,
     *     ultima_asistencia:?string,
     *     facilitadores:array<int, array{
     *         persona_id:?int,
     *         nombre:string,
     *         telefono:?string,
     *         telefono_normalizado:?string
     *     }>
     * }>
     */
    public function obtener(?Carbon $fechaReferencia = null): Collection
    {
        $fechaRef = ($fechaReferencia ?? now())->copy()->startOfDay();

        $grupos = Grupo::query()
            ->where('activo', true)
            ->whereHas('tipoGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%crecimiento%']))
            ->orderBy('nombre')
            ->get();

        $inicioSemanaAnterior = $fechaRef->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
        $finSemanaAnterior = $fechaRef->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);

        return $grupos
            ->map(function (Grupo $grupo) use ($fechaRef, $inicioSemanaAnterior, $finSemanaAnterior): ?array {
                $frecuencia = $grupo->frecuencia_asistencia ?: Grupo::FRECUENCIA_SEMANAL;

                if ($frecuencia === Grupo::FRECUENCIA_MENSUAL) {
                    $inicioMesAnterior = $fechaRef->copy()->subMonthNoOverflow()->startOfMonth();
                    $finMesAnterior = $fechaRef->copy()->subMonthNoOverflow()->endOfMonth();

                    $asistenciaMesAnterior = AsistenciaGrupo::query()
                        ->where('grupo_id', $grupo->id)
                        ->whereBetween('fecha', [$inicioMesAnterior->toDateString(), $finMesAnterior->toDateString()])
                        ->exists();

                    if ($asistenciaMesAnterior) {
                        return null;
                    }

                    return [
                        'grupo_id' => $grupo->id,
                        'grupo' => $grupo->nombre,
                        'frecuencia' => $frecuencia,
                        'periodo_inicio' => $inicioMesAnterior->toDateString(),
                        'periodo_fin' => $finMesAnterior->toDateString(),
                        'ultima_asistencia' => AsistenciaGrupo::query()->where('grupo_id', $grupo->id)->max('fecha'),
                        'facilitadores' => $this->obtenerFacilitadores($grupo, $fechaRef),
                    ];
                }

                $asistenciaSemanaAnterior = AsistenciaGrupo::query()
                    ->where('grupo_id', $grupo->id)
                    ->whereBetween('fecha', [$inicioSemanaAnterior->toDateString(), $finSemanaAnterior->toDateString()])
                    ->exists();

                if ($asistenciaSemanaAnterior) {
                    return null;
                }

                $ultimaAsistencia = AsistenciaGrupo::query()
                    ->where('grupo_id', $grupo->id)
                    ->max('fecha');

                if ($frecuencia === Grupo::FRECUENCIA_QUINCENAL && $ultimaAsistencia) {
                    $inicioSemanaUltimaAsistencia = Carbon::parse($ultimaAsistencia)->startOfWeek(Carbon::MONDAY);
                    $semanasDeDiferencia = $inicioSemanaUltimaAsistencia->diffInWeeks($inicioSemanaAnterior);

                    if ($semanasDeDiferencia % 2 !== 0) {
                        return null;
                    }
                }

                return [
                    'grupo_id' => $grupo->id,
                    'grupo' => $grupo->nombre,
                    'frecuencia' => $frecuencia,
                    'periodo_inicio' => $inicioSemanaAnterior->toDateString(),
                    'periodo_fin' => $finSemanaAnterior->toDateString(),
                    'ultima_asistencia' => $ultimaAsistencia,
                    'facilitadores' => $this->obtenerFacilitadores($grupo, $fechaRef),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<int, array{
     *     persona_id:?int,
     *     nombre:string,
     *     telefono:?string,
     *     telefono_normalizado:?string
     * }>
     */
    protected function obtenerFacilitadores(Grupo $grupo, Carbon $fechaRef): array
    {
        return ParticipacionGrupo::query()
            ->where('grupo_id', $grupo->id)
            ->where(function ($query) use ($fechaRef): void {
                $query->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fechaRef->toDateString());
            })
            ->where(function ($query) use ($fechaRef): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fechaRef->toDateString());
            })
            ->whereHas('rolGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%facilit%']))
            ->with('persona:id,nombre,apellido,telefono,telefono_normalizado')
            ->get()
            ->map(function (ParticipacionGrupo $participacion): array {
                $persona = $participacion->persona;
                $nombreCompleto = $persona ? trim(($persona->apellido ?? '') . ' ' . ($persona->nombre ?? '')) : 'Sin persona';

                return [
                    'persona_id' => $persona?->id,
                    'nombre' => $nombreCompleto,
                    'telefono' => $persona?->telefono,
                    'telefono_normalizado' => $persona?->telefono_normalizado,
                ];
            })
            ->values()
            ->all();
    }
}
