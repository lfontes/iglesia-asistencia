<?php

namespace App\Filament\Widgets;

use App\Models\AsistenciaGrupo;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class AsistenciasSemanalesGruposWidget extends ChartWidget
{
    protected static ?string $heading = 'Asistencias semanales a grupos de crecimiento';

    protected static ?string $description = 'Total de asistencias registradas por semana en los grupos de crecimiento.';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) $user
            && (
                ! $user->isRestrictedPanelUser()
                || $user->hasCombinedFacilitadorLiderAccess()
                || $user->canManageGrupos()
            );
    }

    protected function getData(): array
    {
        $weeks = collect(range(7, 0))
            ->map(function (int $offset): array {
                $start = CarbonImmutable::now()->startOfWeek()->subWeeks($offset);
                $end = $start->endOfWeek();

                return [
                    'key' => $start->format('o-\WW'),
                    'label' => $start->format('d/m').' - '.$end->format('d/m'),
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->values();

        $totals = AsistenciaGrupo::query()
            ->selectRaw("DATE_FORMAT(fecha - INTERVAL WEEKDAY(fecha) DAY, '%x-W%v') as semana")
            ->selectRaw('COUNT(*) as total')
            ->join('grupos', 'grupos.id', '=', 'asistencia_grupos.grupo_id')
            ->join('tipo_grupos', 'tipo_grupos.id', '=', 'grupos.tipo_grupo_id')
            ->where('tipo_grupos.nombre', 'Crecimiento')
            ->where('asistencia_grupos.presente', true)
            ->whereDate('asistencia_grupos.fecha', '>=', $weeks->first()['start']->toDateString())
            ->groupBy('semana')
            ->pluck('total', 'semana');

        return [
            'datasets' => [
                [
                    'label' => 'Asistencias',
                    'data' => $weeks
                        ->map(fn (array $week): int => (int) ($totals[$week['key']] ?? 0))
                        ->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $weeks->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
