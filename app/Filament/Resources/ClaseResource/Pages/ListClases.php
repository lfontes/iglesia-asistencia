<?php

namespace App\Filament\Resources\ClaseResource\Pages;

use App\Filament\Resources\ClaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClases extends ListRecords
{
    protected static string $resource = ClaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
