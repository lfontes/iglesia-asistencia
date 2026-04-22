<?php

namespace App\Filament\Widgets;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class ResumenAsistenciaGruposWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public ?int $grupoId = null;

    public ?int $personaId = null;

    protected function getStats(): array
    {
        $summary = $this->getSummaryData();

        return [
            Stat::make('Grupo', $summary['grupo']?->nombre ?? 'Sin seleccionar')
                ->icon('heroicon-o-user-group'),
            Stat::make('Participantes', (string) $summary['total_personas'])
                ->icon('heroicon-o-users'),
            Stat::make('Reuniones registradas', (string) $summary['total_fechas'])
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Promedio de asistencia', "{$summary['promedio_asistencia']}%")
                ->color('success')
                ->icon('heroicon-o-chart-bar'),
        ];
    }

    /**
     * @return array{grupo:?Grupo,total_personas:int,total_fechas:int,total_presentes:int,promedio_asistencia:int}
     */
    protected function getSummaryData(): array
    {
        $rows = $this->getAttendanceRows();
        $totalFechas = $this->getAttendanceDates()->count();
        $totalPersonas = $rows->count();
        $totalPresentes = $rows->sum('presentes');
        $promedio = $totalPersonas > 0 && $totalFechas > 0
            ? (int) round(($totalPresentes / ($totalPersonas * $totalFechas)) * 100)
            : 0;

        return [
            'grupo' => $this->getSelectedGroup(),
            'total_personas' => $totalPersonas,
            'total_fechas' => $totalFechas,
            'total_presentes' => $totalPresentes,
            'promedio_asistencia' => $promedio,
        ];
    }

    protected function getAttendanceRows(): Collection
    {
        if (! $this->grupoId) {
            return collect();
        }

        $fechas = $this->getAttendanceDates();
        $totalFechas = $fechas->count();

        $participantes = ParticipacionGrupo::query()
            ->where('grupo_id', $this->grupoId)
            ->when($this->personaId, fn ($query) => $query->where('persona_id', $this->personaId))
            ->get()
            ->unique('persona_id')
            ->values();

        $agregados = AsistenciaGrupo::query()
            ->selectRaw('persona_id')
            ->selectRaw('SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) AS presentes')
            ->where('grupo_id', $this->grupoId)
            ->groupBy('persona_id')
            ->get()
            ->keyBy('persona_id');

        return $participantes
            ->map(function (ParticipacionGrupo $participacion) use ($agregados, $totalFechas): array {
                $agregado = $agregados->get($participacion->persona_id);
                $presentes = (int) ($agregado->presentes ?? 0);

                return [
                    'presentes' => $presentes,
                    'ausencias' => max($totalFechas - $presentes, 0),
                ];
            })
            ->values();
    }

    protected function getAttendanceDates(): Collection
    {
        if (! $this->grupoId) {
            return collect();
        }

        return AsistenciaGrupo::query()
            ->where('grupo_id', $this->grupoId)
            ->distinct()
            ->orderBy('fecha')
            ->pluck('fecha');
    }

    protected function getSelectedGroup(): ?Grupo
    {
        if (! $this->grupoId) {
            return null;
        }

        return Grupo::query()->find($this->grupoId);
    }
}
