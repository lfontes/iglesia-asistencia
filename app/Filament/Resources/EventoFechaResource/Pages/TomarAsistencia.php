<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use App\Models\Asistencia;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\EventoFechaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class TomarAsistencia extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = EventoFechaResource::class;

    protected static string $view = 'filament.resources.evento-fecha-resource.pages.tomar-asistencia';

    /** @var array<int, int|string> */
    public array $presentes = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->presentes = Asistencia::query()
            ->where('evento_fecha_id', $this->getRecord()->id)
            ->where('presente', true)
            ->pluck('persona_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('presentes')
                ->label('Personas presentes')
                ->multiple()
                ->searchable()
                ->preload(false)
                ->getSearchResultsUsing(fn (string $search): array => Persona::query()
                    ->buscarPorNombreApellido($search)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Persona $persona): array => [
                        $persona->id => $this->personaLabel($persona),
                    ])
                    ->all())
                ->getOptionLabelsUsing(fn (array $values): array => Persona::query()
                    ->whereIn('id', $values)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get()
                    ->mapWithKeys(fn (Persona $persona): array => [
                        $persona->id => $this->personaLabel($persona),
                    ])
                    ->all()),
        ]);
    }

    public function guardar(): void
    {
        $eventoFecha = $this->getRecord();
        $presentesIds = collect($this->presentes)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $ausentesQuery = Asistencia::query()
            ->where('evento_fecha_id', $eventoFecha->id);

        if ($presentesIds->isNotEmpty()) {
            $ausentesQuery->whereNotIn('persona_id', $presentesIds->all());
        }

        $ausentesQuery->delete();

        /** @var int $personaId */
        foreach ($presentesIds as $personaId) {
            Asistencia::updateOrCreate(
                [
                    'persona_id' => $personaId,
                    'evento_fecha_id' => $eventoFecha->id,
                ],
                [
                    'presente' => true,
                ]
            );
        }

        Notification::make()
            ->title('Asistencia guardada correctamente')
            ->success()
            ->send();
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->apellido} {$persona->nombre}");
    }
}
