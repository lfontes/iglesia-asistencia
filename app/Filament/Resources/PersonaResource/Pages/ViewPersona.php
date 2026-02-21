<?php

namespace App\Filament\Resources\PersonaResource\Pages;

use App\Filament\Resources\PersonaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPersona extends ViewRecord
{
    protected static string $resource = PersonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
