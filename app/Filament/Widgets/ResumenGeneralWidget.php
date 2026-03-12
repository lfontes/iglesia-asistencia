<?php

namespace App\Filament\Widgets;

use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Persona;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenGeneralWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $currentYear = (int) date('Y');
        $eventosAnioActual = Evento::query()
            ->whereHas('fechas', fn ($query) => $query->whereYear('fecha', $currentYear))
            ->count();

        $gruposPorTipo = Grupo::query()
            ->leftJoin('tipo_grupos', 'grupos.tipo_grupo_id', '=', 'tipo_grupos.id')
            ->where('grupos.anio', $currentYear)
            ->selectRaw("COALESCE(tipo_grupos.nombre, 'Sin tipo') as tipo_nombre")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('tipo_nombre')
            ->orderBy('tipo_nombre')
            ->get();

        $stats = [
            Stat::make('Total de personas', (string) Persona::count())
                ->icon('heroicon-o-users'),
            Stat::make("Total de eventos ({$currentYear})", (string) $eventosAnioActual)
                ->icon('heroicon-o-calendar-days'),
            Stat::make("Grupos {$currentYear}", (string) $gruposPorTipo->sum('total'))
                ->description(
                    $gruposPorTipo->isEmpty()
                        ? 'Sin grupos registrados para este anio.'
                        : $gruposPorTipo
                            ->map(fn ($item): string => "{$item->tipo_nombre}: {$item->total}")
                            ->join(' | ')
                )
                ->icon('heroicon-o-user-group'),
        ];

        return $stats;
    }
}
