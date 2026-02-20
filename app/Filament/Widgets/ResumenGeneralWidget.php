<?php

namespace App\Filament\Widgets;

use App\Models\Evento;
use App\Models\Persona;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenGeneralWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total de personas', (string) Persona::count())
                ->icon('heroicon-o-users'),
            Stat::make('Total de eventos', (string) Evento::count())
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
