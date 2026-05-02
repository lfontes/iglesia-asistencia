<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;
use App\Filament\Resources\EventoFechaResource;
use App\Models\Asistencia;
use App\Models\EventoInscripcion;
use App\Models\Persona;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TomarAsistencia extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = EventoFechaResource::class;

    protected string $view = 'filament.resources.evento-fecha-resource.pages.tomar-asistencia';

    /** @var array<int, int|string> */
    public array $presentes = [];

    public function mount(int|string $record): void
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

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('presentes')
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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getInscriptosQuery())
            ->heading('Inscriptos')
            ->description('Personas registradas previamente para esta fecha de evento.')
            ->defaultSort('created_at')
            ->emptyStateHeading('Todavía no hay inscripciones para esta fecha')
            ->emptyStateDescription('Cuando lleguen inscripciones desde el formulario público, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-ticket')
            ->headerActions([
                Action::make('abrir_formulario_publico')
                    ->label('Abrir formulario público')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url($this->getFormularioInscripcionUrl())
                    ->openUrlInNewTab(),
            ])
            ->columns([
                TextColumn::make('persona.apellido')
                    ->label('Persona')
                    ->state(fn (EventoInscripcion $record): string => trim(($record->persona->apellido ?? '').' '.($record->persona->nombre ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'persona',
                            fn (Builder $personaQuery): Builder => $personaQuery->buscarPorNombreApellido($search)
                        );
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->select('evento_inscripciones.*')
                            ->leftJoin('personas', 'personas.id', '=', 'evento_inscripciones.persona_id')
                            ->orderBy('personas.apellido', $direction)
                            ->orderBy('personas.nombre', $direction);
                    })
                    ->weight('medium'),
                TextColumn::make('persona.telefono')
                    ->label('Teléfono')
                    ->placeholder('-'),
                TextColumn::make('persona.email')
                    ->label('Email')
                    ->placeholder('-'),
                TextColumn::make('asistencia_estado')
                    ->label('Estado')
                    ->state(fn (EventoInscripcion $record): string => $this->isInscriptoPresente((int) $record->persona_id) ? 'Presente' : 'Ausente')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Presente' ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('marcar_presente')
                    ->label('Marcar presente')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (EventoInscripcion $record): bool => ! $this->isInscriptoPresente((int) $record->persona_id))
                    ->action(fn (EventoInscripcion $record): mixed => $this->marcarInscriptoPresente((int) $record->persona_id)),
                Action::make('quitar_presente')
                    ->label('Quitar presente')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (EventoInscripcion $record): bool => $this->isInscriptoPresente((int) $record->persona_id))
                    ->action(fn (EventoInscripcion $record): mixed => $this->quitarInscriptoPresente((int) $record->persona_id)),
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
     * @return Collection<int, EventoInscripcion>
     */
    public function getInscriptos(): Collection
    {
        return $this->getInscriptosQuery()
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
        return $this->getRecord()->publicInscriptionUrl();
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

    protected function getInscriptosQuery(): Builder
    {
        return EventoInscripcion::query()
            ->with('persona:id,nombre,apellido,telefono,email')
            ->where('evento_fecha_id', $this->getRecord()->id)
            ->where('estado', 'inscripto');
    }
}
