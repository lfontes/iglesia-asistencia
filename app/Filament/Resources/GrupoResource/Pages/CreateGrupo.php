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

        if ($user?->hasRole('lider') && filled($user->persona_id) && ! $user->canManageGrupos()) {
            $data['lider_persona_id'] = $user->persona_id;
        }

        return $data;
    }
}
