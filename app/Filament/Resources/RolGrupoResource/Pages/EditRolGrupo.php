<?php

namespace App\Filament\Resources\RolGrupoResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\RolGrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRolGrupo extends EditRecord
{
    protected static string $resource = RolGrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
