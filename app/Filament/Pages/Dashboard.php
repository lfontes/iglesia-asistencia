<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Inicio';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user
            && (
                ! $user->isRestrictedPanelUser()
                || $user->hasCombinedFacilitadorLiderAccess()
                || $user->canManageGrupos()
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
