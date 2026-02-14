<?php

namespace App\Filament\Resources\ClaseResource\Pages;

use App\Filament\Resources\ClaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClase extends EditRecord
{
    protected static string $resource = ClaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
