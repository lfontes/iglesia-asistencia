<?php

namespace App\Filament\Resources\IpnNinoResource\Pages;

use App\Filament\Resources\IpnNinoResource;
use Filament\Resources\Pages\EditRecord;

class EditIpnNino extends EditRecord
{
    protected static string $resource = IpnNinoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['es_menor'] = true;

        return $data;
    }
}
