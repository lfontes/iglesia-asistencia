<?php

namespace App\Filament\Widgets;

use App\Models\IpnAsistencia;
use App\Models\IpnAula;
use App\Models\Persona;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IpnResumenWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $user = auth()->user();
        $aulaIds = $user?->ipnAulasDisponibles()->pluck('id') ?? collect();

        $ultimaFecha = IpnAsistencia::query()
            ->whereIn('ipn_aula_id', $aulaIds)
            ->max('fecha');

        $presentesUltimaFecha = $ultimaFecha
            ? IpnAsistencia::query()
                ->whereIn('ipn_aula_id', $aulaIds)
                ->whereDate('fecha', $ultimaFecha)
                ->where('presente', true)
                ->count()
            : 0;

        $totalRegistros30Dias = IpnAsistencia::query()
            ->whereIn('ipn_aula_id', $aulaIds)
            ->whereDate('fecha', '>=', now()->subDays(30)->toDateString())
            ->count();

        $presentes30Dias = IpnAsistencia::query()
            ->whereIn('ipn_aula_id', $aulaIds)
            ->whereDate('fecha', '>=', now()->subDays(30)->toDateString())
            ->where('presente', true)
            ->count();

        $ninos = $user?->canManageAllIpnAulas()
            ? Persona::query()->where('es_menor', true)->count()
            : Persona::query()
                ->where('es_menor', true)
                ->whereHas('ipnParticipaciones', fn ($query) => $query->whereIn('ipn_aula_id', $aulaIds))
                ->count();

        $aulas = IpnAula::query()
            ->whereIn('id', $aulaIds)
            ->where('activo', true)
            ->count();

        $sinAula = $user?->canManageAllIpnAulas()
            ? Persona::query()
                ->where('es_menor', true)
                ->whereDoesntHave('ipnParticipaciones', function ($query): void {
                    $query->where('activo', true)
                        ->where(function ($activeQuery): void {
                            $activeQuery->whereNull('fecha_fin')
                                ->orWhereDate('fecha_fin', '>=', now()->toDateString());
                        });
                })
                ->count()
            : 0;

        return [
            Stat::make('Niños IPN', (string) $ninos)
                ->icon('heroicon-o-user-group'),
            Stat::make('Aulas activas', (string) $aulas)
                ->icon('heroicon-o-home-modern'),
            Stat::make('Presentes última fecha', (string) $presentesUltimaFecha)
                ->description($ultimaFecha ? \Illuminate\Support\Carbon::parse($ultimaFecha)->format('d/m/Y') : 'Sin registros')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Promedio 30 días', "{$this->promedio($presentes30Dias, $totalRegistros30Dias)}%")
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Niños sin aula', (string) $sinAula)
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    protected function promedio(int $presentes, int $total): int
    {
        if ($total === 0) {
            return 0;
        }

        return (int) round(($presentes / $total) * 100);
    }
}
