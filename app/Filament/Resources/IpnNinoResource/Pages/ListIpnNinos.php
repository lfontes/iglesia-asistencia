<?php

namespace App\Filament\Resources\IpnNinoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\IpnNinoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIpnNinos extends ListRecords
{
    protected static string $resource = IpnNinoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
