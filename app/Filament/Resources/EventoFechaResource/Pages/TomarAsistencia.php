<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use App\Models\Asistencia;
use App\Models\EventoInscripcion;
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

    public function marcarInscriptoPresente(int $personaId): void
    {
        Asistencia::updateOrCreate(
            [
                'persona_id' => $personaId,
                'evento_fecha_id' => $this->getRecord()->id,
            ],
            [
                'presente' => true,
            ]
        );

        $this->sincronizarPresentesDesdeBase();

        Notification::make()
            ->title('Inscripto marcado como presente')
            ->success()
            ->send();
    }

    public function quitarInscriptoPresente(int $personaId): void
    {
        Asistencia::query()
            ->where('persona_id', $personaId)
            ->where('evento_fecha_id', $this->getRecord()->id)
            ->delete();

        $this->sincronizarPresentesDesdeBase();

        Notification::make()
            ->title('Presente quitado')
            ->color('gray')
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, EventoInscripcion>
     */
    public function getInscriptos(): \Illuminate\Support\Collection
    {
        return EventoInscripcion::query()
            ->with('persona:id,nombre,apellido,telefono,email')
            ->where('evento_fecha_id', $this->getRecord()->id)
            ->where('estado', 'inscripto')
            ->orderBy('created_at')
            ->get();
    }

    public function getTotalInscriptos(): int
    {
        return $this->getInscriptos()->count();
    }

    public function getTotalPresentes(): int
    {
        return count($this->presentes);
    }

    public function getTotalInscriptosPresentes(): int
    {
        return $this->getInscriptos()
            ->filter(fn (EventoInscripcion $inscripcion): bool => $this->isInscriptoPresente((int) $inscripcion->persona_id))
            ->count();
    }

    public function getTotalPresentesNoInscriptos(): int
    {
        return collect($this->presentes)
            ->diff($this->getInscriptos()->pluck('persona_id'))
            ->count();
    }

    public function getFormularioInscripcionUrl(): string
    {
        return route('eventos.inscripcion.create', $this->getRecord());
    }

    public function isInscriptoPresente(int $personaId): bool
    {
        return in_array($personaId, $this->presentes, true);
    }

    protected function sincronizarPresentesDesdeBase(): void
    {
        $this->presentes = Asistencia::query()
            ->where('evento_fecha_id', $this->getRecord()->id)
            ->where('presente', true)
            ->pluck('persona_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->apellido} {$persona->nombre}");
    }
}
