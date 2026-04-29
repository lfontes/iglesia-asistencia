<?php

namespace App\Filament\Resources\PersonaResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\PersonaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonas extends ListRecords
{
    protected static string $resource = PersonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
