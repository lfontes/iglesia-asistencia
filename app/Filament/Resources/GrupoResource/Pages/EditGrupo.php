<?php

namespace App\Filament\Resources\GrupoResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\GrupoResource;
use App\Models\Grupo;
use App\Models\TipoGrupo;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGrupo extends EditRecord
{
    protected static string $resource = GrupoResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless(auth()->user()?->canManageGrupo($this->record), 403);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $esMinisterio = TipoGrupo::query()
            ->whereKey($data['tipo_grupo_id'] ?? null)
            ->whereRaw('LOWER(nombre) = ?', ['ministerio'])
            ->exists();

        if ($esMinisterio) {
            $data['frecuencia_asistencia'] = Grupo::FRECUENCIA_SIN_ASISTENCIA;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('participacion')
                ->label('Registrar participantes')
                ->url(fn () => GrupoResource::getUrl('participacion', ['record' => $this->record]))
                ->icon('heroicon-o-user-plus'),
            DeleteAction::make(),
        ];
    }
}
