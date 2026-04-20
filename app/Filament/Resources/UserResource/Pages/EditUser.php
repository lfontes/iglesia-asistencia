<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_names'] = $this->record->roles()->pluck('name')->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $roles = collect($this->data['role_names'] ?? [])
            ->filter()
            ->values()
            ->all();

        if ($roles !== []) {
            $this->record->syncRoles($roles);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
