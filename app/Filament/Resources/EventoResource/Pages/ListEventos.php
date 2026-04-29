<?php

namespace App\Filament\Resources\EventoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\EventoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventos extends ListRecords
{
    protected static string $resource = EventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
