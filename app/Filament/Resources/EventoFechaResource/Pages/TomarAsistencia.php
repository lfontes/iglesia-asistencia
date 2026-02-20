<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use App\Models\Persona;
use App\Models\Asistencia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Notifications\Notification;
use App\Filament\Resources\EventoFechaResource;

class TomarAsistencia extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = EventoFechaResource::class;

    protected static string $view = 'filament.resources.evento-fecha-resource.pages.tomar-asistencia';

    public array $asistencias = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $eventoFecha = $this->getRecord();

        $personas = Persona::all();

        foreach ($personas as $persona) {
            $asistenciaExistente = Asistencia::where('persona_id', $persona->id)
                ->where('evento_fecha_id', $eventoFecha->id)
                ->first();

            $this->asistencias[$persona->id] = [
                'persona_id' => $persona->id,
                'nombre_completo' => trim("{$persona->apellido} {$persona->nombre}"),
                'presente' => $asistenciaExistente?->presente ?? false,
            ];
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Repeater::make('asistencias')
                ->schema([
                    Forms\Components\Hidden::make('persona_id'),
                    Forms\Components\Hidden::make('nombre_completo'),

                    Forms\Components\Toggle::make('presente')
                        ->label(fn ($state, $get) => $get('nombre_completo')),
                ])
                ->columns(1)
                ->disableItemCreation()
                ->disableItemDeletion(),
        ]);
    }

    public function guardar()
    {
        $eventoFecha = $this->getRecord();

        foreach ($this->asistencias as $data) {
            Asistencia::updateOrCreate(
                [
                    'persona_id' => $data['persona_id'],
                    'evento_fecha_id' => $eventoFecha->id,
                ],
                [
                    'presente' => $data['presente'],
                ]
            );
        }

        Notification::make()
            ->title('Asistencia guardada correctamente')
            ->success()
            ->send();
    }
}
