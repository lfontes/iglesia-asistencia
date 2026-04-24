<?php

namespace App\Filament\Resources\GrupoResource\Pages;

use App\Filament\Resources\GrupoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGrupo extends CreateRecord
{
    protected static string $resource = GrupoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        $data['created_by'] = $user?->id;

        return $data;
    }
}
