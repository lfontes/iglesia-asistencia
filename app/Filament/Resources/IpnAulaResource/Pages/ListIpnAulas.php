<?php

namespace App\Filament\Resources\IpnAulaResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\IpnAulaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIpnAulas extends ListRecords
{
    protected static string $resource = IpnAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
