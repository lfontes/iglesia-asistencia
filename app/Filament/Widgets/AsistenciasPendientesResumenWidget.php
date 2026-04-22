<?php

namespace App\Filament\Widgets;

use App\Models\Grupo;
use App\Services\AsistenciasPendientesService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AsistenciasPendientesResumenWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public ?string $fecha = null;

    protected function getStats(): array
    {
        $summary = $this->getSummary();

        return [
            Stat::make('Grupos pendientes', (string) $summary['total_grupos'])
                ->icon('heroicon-o-user-group'),
            Stat::make('Facilitadores detectados', (string) $summary['total_facilitadores'])
                ->icon('heroicon-o-users'),
            Stat::make('Sin teléfono', (string) $summary['sin_telefono'])
                ->color($summary['sin_telefono'] > 0 ? 'warning' : 'success')
                ->icon($summary['sin_telefono'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
            Stat::make('Frecuencias', "Semanales: {$summary['semanales']} · Quincenales: {$summary['quincenales']} · Mensuales: {$summary['mensuales']}")
                ->icon('heroicon-o-calendar-days'),
        ];
    }

    /**
     * @return array{total_grupos:int,total_facilitadores:int,sin_telefono:int,semanales:int,quincenales:int,mensuales:int}
     */
    protected function getSummary(): array
    {
        $pendientes = app(AsistenciasPendientesService::class)
            ->obtener($this->getFechaReferencia());

        return [
            'total_grupos' => $pendientes->count(),
            'total_facilitadores' => $pendientes->sum(fn (array $item): int => count($item['facilitadores'])),
            'sin_telefono' => $pendientes->sum(
                fn (array $item): int => collect($item['facilitadores'])->filter(fn (array $facilitador): bool => blank($facilitador['telefono']))->count()
            ),
            'semanales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_SEMANAL)->count(),
            'quincenales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_QUINCENAL)->count(),
            'mensuales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_MENSUAL)->count(),
        ];
    }

    protected function getFechaReferencia(): Carbon
    {
        try {
            return filled($this->fecha)
                ? Carbon::parse((string) $this->fecha)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
