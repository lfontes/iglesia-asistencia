<?php

namespace App\Filament\Resources\MetagrupoResource\Pages;

use App\Filament\Resources\MetagrupoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMetagrupo extends EditRecord
{
    protected static string $resource = MetagrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => (bool) auth()->user()?->hasRole('admin')),
        ];
    }
}
