<?php

namespace App\Filament\Resources\GrupoResource\Pages;

use App\Filament\Resources\GrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGrupo extends EditRecord
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('participacion')
                ->label('Registrar Participacion')
                ->url(fn () => GrupoResource::getUrl('participacion', ['record' => $this->record]))
                ->icon('heroicon-o-user-plus'),
            Actions\DeleteAction::make(),
        ];
    }
}
