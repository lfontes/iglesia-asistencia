<?php

namespace App\Filament\Resources\PersonaResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PersonaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersona extends EditRecord
{
    protected static string $resource = PersonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
