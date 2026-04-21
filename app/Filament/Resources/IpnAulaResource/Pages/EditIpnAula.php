<?php

namespace App\Filament\Resources\IpnAulaResource\Pages;

use App\Filament\Resources\IpnAulaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIpnAula extends EditRecord
{
    protected static string $resource = IpnAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
