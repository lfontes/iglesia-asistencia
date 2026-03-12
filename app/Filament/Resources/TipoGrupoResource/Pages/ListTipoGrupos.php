<?php

namespace App\Filament\Resources\TipoGrupoResource\Pages;

use App\Filament\Resources\TipoGrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoGrupos extends ListRecords
{
    protected static string $resource = TipoGrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

