<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use App\Filament\Resources\EventoFechaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventoFechas extends ListRecords
{
    protected static string $resource = EventoFechaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
