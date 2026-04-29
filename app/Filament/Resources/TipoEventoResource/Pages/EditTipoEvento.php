<?php

namespace App\Filament\Resources\TipoEventoResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\TipoEventoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoEvento extends EditRecord
{
    protected static string $resource = TipoEventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
