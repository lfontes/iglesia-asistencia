<?php

namespace App\Filament\Widgets;

use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Persona;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenGeneralWidget extends BaseWidget
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) $user
            && (
                ! $user->isRestrictedPanelUser()
                || $user->hasCombinedFacilitadorLiderAccess()
            );
    }

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
        $personasPorTipo = Grupo::query()
            ->leftJoin('tipo_grupos', 'grupos.tipo_grupo_id', '=', 'tipo_grupos.id')
            ->leftJoin('participacion_grupos', 'participacion_grupos.grupo_id', '=', 'grupos.id')
            ->where('grupos.anio', $currentYear)
            ->where(function ($query): void {
                $query->whereNull('participacion_grupos.fecha_fin')
                    ->orWhere('participacion_grupos.fecha_fin', '>=', now()->toDateString());
            })
            ->selectRaw("COALESCE(tipo_grupos.nombre, 'Sin tipo') as tipo_nombre")
            ->selectRaw('COUNT(DISTINCT participacion_grupos.persona_id) as total_personas')
            ->groupBy('tipo_nombre')
            ->orderBy('tipo_nombre')
            ->get()
            ->keyBy('tipo_nombre');

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
                            ->map(function ($item) use ($personasPorTipo): string {
                                $personas = (int) ($personasPorTipo->get($item->tipo_nombre)->total_personas ?? 0);

                                return "{$item->tipo_nombre}: {$item->total} grupos / {$personas} personas";
                            })
                            ->join(' | ')
                )
                ->icon('heroicon-o-user-group'),
        ];

        return $stats;
    }
}
