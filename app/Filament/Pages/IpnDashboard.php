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
        $ultimaFecha = IpnAsistencia::query()
            ->max('fecha');

        $asistenciasUltimaFecha = $ultimaFecha
            ? IpnAsistencia::query()
                ->whereDate('fecha', $ultimaFecha)
                ->where('presente', true)
                ->count()
            : 0;

        $totalRegistros30Dias = IpnAsistencia::query()
            ->whereDate('fecha', '>=', now()->subDays(30)->toDateString())
            ->count();

        $presentes30Dias = IpnAsistencia::query()
            ->whereDate('fecha', '>=', now()->subDays(30)->toDateString())
            ->where('presente', true)
            ->count();

        return [
            'ninos' => Persona::query()->where('es_menor', true)->count(),
            'aulas' => IpnAula::query()->where('activo', true)->count(),
            'presentes_ultima_fecha' => $asistenciasUltimaFecha,
            'ultima_fecha' => $ultimaFecha,
            'promedio_30_dias' => $totalRegistros30Dias > 0
                ? (int) round(($presentes30Dias / $totalRegistros30Dias) * 100)
                : 0,
            'sin_aula' => Persona::query()
                ->where('es_menor', true)
                ->whereDoesntHave('ipnParticipaciones', function ($query): void {
                    $query->where('activo', true)
                        ->where(function ($activeQuery): void {
                            $activeQuery->whereNull('fecha_fin')
                                ->orWhereDate('fecha_fin', '>=', now()->toDateString());
                        });
                })
                ->count(),
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
