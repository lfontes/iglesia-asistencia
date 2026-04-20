<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $roles = collect($this->data['role_names'] ?? [])
            ->filter()
            ->values()
            ->all();

        if ($roles !== []) {
            $this->record->syncRoles($roles);
        }
    }
}
