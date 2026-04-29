<?php

namespace App\Filament\Resources\RolGrupoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\RolGrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRolGrupos extends ListRecords
{
    protected static string $resource = RolGrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
