<?php

namespace App\Filament\Resources\MetagrupoResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MetagrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMetagrupo extends EditRecord
{
    protected static string $resource = MetagrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
