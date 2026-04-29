<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\IpnResumenWidget;
use Filament\Pages\Page;

class IpnDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home-modern';

    protected static string | \UnitEnum | null $navigationGroup = 'IPN';

    protected static ?string $navigationLabel = 'Inicio IPN';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Inicio IPN';

    protected string $view = 'filament.pages.ipn-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAccessIpn();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IpnResumenWidget::class,
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
