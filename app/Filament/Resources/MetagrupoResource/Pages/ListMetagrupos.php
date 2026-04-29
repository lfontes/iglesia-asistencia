<?php

namespace App\Filament\Resources\MetagrupoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MetagrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMetagrupos extends ListRecords
{
    protected static string $resource = MetagrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
