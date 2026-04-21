<?php

namespace App\Filament\Pages;

use App\Models\IpnAsistencia;
use App\Models\IpnAula;
use App\Models\IpnAulaPersona;
use App\Models\Persona;
use Filament\Pages\Page;

class IpnDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'IPN';

    protected static ?string $navigationLabel = 'Inicio IPN';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Inicio IPN';

    protected static string $view = 'filament.pages.ipn-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAccessIpn();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getStats(): array
    {
        $user = auth()->user();
        $aulaIds = $user?->ipnAulasDisponibles()->pluck('id') ?? collect();

        $ultimaFecha = IpnAsistencia::query()
            ->whereIn('ipn_aula_id', $aulaIds)
            ->max('fecha');

        $asistenciasUltimaFecha = $ultimaFecha
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

        return [
            'ninos' => $user?->canManageAllIpnAulas()
                ? Persona::query()->where('es_menor', true)->count()
                : Persona::query()
                    ->where('es_menor', true)
                    ->whereHas('ipnParticipaciones', fn ($query) => $query->whereIn('ipn_aula_id', $aulaIds))
                    ->count(),
            'aulas' => IpnAula::query()
                ->whereIn('id', $aulaIds)
                ->where('activo', true)
                ->count(),
            'presentes_ultima_fecha' => $asistenciasUltimaFecha,
            'ultima_fecha' => $ultimaFecha,
            'promedio_30_dias' => $totalRegistros30Dias > 0
                ? (int) round(($presentes30Dias / $totalRegistros30Dias) * 100)
                : 0,
            'sin_aula' => $user?->canManageAllIpnAulas()
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
                : 0,
        ];
    }

    public function getTomarAsistenciaUrl(): string
    {
        return IpnTomarAsistencia::getUrl();
    }

    public function getReporteUrl(): string
    {
        return IpnReporteAsistencia::getUrl();
    }
}
