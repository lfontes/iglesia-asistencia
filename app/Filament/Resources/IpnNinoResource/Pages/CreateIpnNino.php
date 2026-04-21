<?php

namespace App\Filament\Resources\IpnNinoResource\Pages;

use App\Filament\Resources\IpnNinoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIpnNino extends CreateRecord
{
    protected static string $resource = IpnNinoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['es_menor'] = true;

        return $data;
    }
}
