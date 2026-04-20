<?php

namespace App\Filament\Pages;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\Persona;
use App\Models\RolGrupo;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class AsistenciaGruposCrecimiento extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Asistencia';

    protected static ?string $navigationLabel = 'Asistencia Gr. Crecimiento';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.asistencia-grupos-crecimiento';

    protected static ?string $title = 'Asistencia Grupos de Crecimiento';

    public ?int $grupo_id = null;

    public ?string $fecha = null;

    /** @var array<int, int|string> */
    public array $presentes = [];

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $grupoIdDesdeUrl = request()->integer('grupo_id');
        $this->grupo_id = $grupoIdDesdeUrl ?: null;

        $this->refrescarAsistenciaCargada();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'facilitador']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Filtro')
                ->schema([
                    Forms\Components\Select::make('grupo_id')
                        ->label('Grupo de crecimiento')
                        ->placeholder('Selecciona tu grupo')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => $this->gruposDeCrecimientoQuery()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->get()
                            ->mapWithKeys(fn (Grupo $grupo): array => [
                                $grupo->id => $this->grupoOptionLabel($grupo),
                            ])
                            ->all())
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->refrescarAsistenciaCargada();
                        }),

                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->required()
                        ->default(now()->toDateString())
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->refrescarAsistenciaCargada();
                        }),
                ])
                ->columns(2),

            Forms\Components\Section::make('Integrantes del grupo')
                ->schema([
                    Forms\Components\CheckboxList::make('presentes')
                        ->label('Marcar presentes')
                        ->options(fn (): array => $this->integrantesOptions())
                        ->columns(2)
                        ->bulkToggleable()
                        ->helperText('Solo se muestran integrantes del grupo seleccionado para la fecha indicada.')
                        ->searchable(),
                ])
                ->visible(fn () => filled($this->grupo_id)),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('agregarPersona')
                ->label('Agregar persona al grupo')
                ->icon('heroicon-o-user-plus')
                ->disabled(fn (): bool => blank($this->grupo_id))
                ->form([
                    Forms\Components\Select::make('persona_id')
                        ->label('Persona existente')
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
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $persona = Persona::query()->whereKey($value)->first();

                            return $persona ? $this->personaLabel($persona) : null;
                        })
                        ->helperText('Selecciona una persona existente o crea una nueva debajo.'),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre (nueva persona)')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('apellido')
                        ->label('Apellido (nueva persona)')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('telefono')
                        ->label('Telefono (opcional)')
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('fecha_nacimiento')
                        ->label('Fecha de nacimiento')
                        ->native(false),

                    Forms\Components\Select::make('rol_grupo_id')
                        ->label('Rol en el grupo (opcional)')
                        ->placeholder('Sin rol')
                        ->searchable()
                        ->options(fn (): array => RolGrupo::query()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                            ->all()),
                ])
                ->action(function (array $data): void {
                    $this->agregarPersonaAlGrupo($data);
                }),
            Actions\Action::make('quitarPersona')
                ->label('Quitar persona del grupo')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->disabled(fn (): bool => blank($this->grupo_id))
                ->form([
                    Forms\Components\Select::make('persona_id')
                        ->label('Persona')
                        ->options(fn (): array => $this->integrantesOptions())
                        ->searchable()
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Quitar persona del grupo')
                ->modalDescription('La persona dejará de figurar como integrante activa del grupo, pero se conservará su historial de asistencias.')
                ->action(function (array $data): void {
                    $this->quitarPersonaDelGrupo((int) $data['persona_id']);
                }),
        ];
    }

    public function guardar(): void
    {
        $state = $this->form->getState();

        $grupoId = isset($state['grupo_id']) ? (int) $state['grupo_id'] : null;
        $fecha = $state['fecha'] ?? null;

        if (! $grupoId || ! $fecha) {
            Notification::make()
                ->title('Debe seleccionar un grupo y una fecha')
                ->danger()
                ->send();

            return;
        }

        $integrantesIds = $this->integrantesIds();

        $presentesIds = collect($this->presentes)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->intersect($integrantesIds)
            ->values();

        $query = AsistenciaGrupo::query()
            ->where('grupo_id', $grupoId)
            ->whereDate('fecha', $fecha);

        if ($presentesIds->isNotEmpty()) {
            $query->whereNotIn('persona_id', $presentesIds->all());
        }

        $query->delete();

        /** @var int $personaId */
        foreach ($presentesIds as $personaId) {
            AsistenciaGrupo::updateOrCreate(
                [
                    'grupo_id' => $grupoId,
                    'persona_id' => $personaId,
                    'fecha' => $fecha,
                ],
                [
                    'presente' => true,
                    'created_by' => auth()->id(),
                ]
            );
        }

        Notification::make()
            ->title('Asistencia guardada correctamente')
            ->success()
            ->send();
    }

    protected function agregarPersonaAlGrupo(array $data): void
    {
        if (! $this->grupo_id) {
            Notification::make()
                ->title('Selecciona primero un grupo de crecimiento')
                ->warning()
                ->send();

            return;
        }

        $personaId = filled($data['persona_id'] ?? null)
            ? (int) $data['persona_id']
            : null;

        if (! $personaId) {
            $nombre = trim((string) ($data['nombre'] ?? ''));
            $apellido = trim((string) ($data['apellido'] ?? ''));

            if ($nombre === '' || $apellido === '') {
                Notification::make()
                    ->title('Selecciona una persona existente o ingresa nombre y apellido')
                    ->danger()
                    ->send();

                return;
            }

            $persona = Persona::create([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'telefono' => ($data['telefono'] ?? null) ?: null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            ]);

            $personaId = (int) $persona->id;
        }

        $grupo = Grupo::query()->findOrFail($this->grupo_id);

        ParticipacionGrupo::updateOrCreate(
            [
                'persona_id' => $personaId,
                'grupo_id' => $grupo->id,
                'rol_grupo_id' => filled($data['rol_grupo_id'] ?? null) ? (int) $data['rol_grupo_id'] : null,
            ],
            [
                'anio' => $grupo->anio,
                'fecha_inicio' => $this->fecha,
                'fecha_fin' => null,
            ]
        );

        $this->refrescarAsistenciaCargada();

        Notification::make()
            ->title('Persona agregada al grupo correctamente')
            ->success()
            ->send();
    }

    public function quitarPersonaDelGrupo(int $personaId): void
    {
        if (! $this->grupo_id) {
            Notification::make()
                ->title('Selecciona primero un grupo de crecimiento')
                ->warning()
                ->send();

            return;
        }

        $fechaFin = $this->fecha ?: now()->toDateString();

        $participaciones = ParticipacionGrupo::query()
            ->where('grupo_id', $this->grupo_id)
            ->where('persona_id', $personaId)
            ->where(function (Builder $query): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $this->fecha ?: now()->toDateString());
            })
            ->get();

        if ($participaciones->isEmpty()) {
            Notification::make()
                ->title('La persona ya no figura como integrante activa')
                ->warning()
                ->send();

            return;
        }

        $tieneAsistencias = AsistenciaGrupo::query()
            ->where('grupo_id', $this->grupo_id)
            ->where('persona_id', $personaId)
            ->exists();

        if ($tieneAsistencias) {
            foreach ($participaciones as $participacion) {
                $participacion->fecha_fin = $fechaFin;

                if ($participacion->fecha_inicio && $participacion->fecha_inicio->gt($participacion->fecha_fin)) {
                    $participacion->fecha_inicio = $participacion->fecha_fin;
                }

                $participacion->save();
            }
        } else {
            $participaciones->each->delete();
        }

        $this->presentes = collect($this->presentes)
            ->reject(fn ($id): bool => (int) $id === $personaId)
            ->values()
            ->all();

        $this->refrescarAsistenciaCargada();

        $persona = Persona::query()->find($personaId);

        Notification::make()
            ->title($tieneAsistencias ? 'Persona quitada del grupo' : 'Persona eliminada del grupo')
            ->body($persona ? $this->personaLabel($persona) : null)
            ->success()
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,label:string}>
     */
    public function integrantesActivos(): \Illuminate\Support\Collection
    {
        $integrantesIds = $this->integrantesIds();

        if ($integrantesIds === []) {
            return collect();
        }

        return Persona::query()
            ->whereIn('id', $integrantesIds)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Persona $persona): array => [
                'id' => (int) $persona->id,
                'label' => $this->personaLabel($persona),
            ])
            ->values();
    }

    protected function refrescarAsistenciaCargada(): void
    {
        if (! $this->grupo_id || ! $this->fecha) {
            $this->presentes = [];

            return;
        }

        $integrantesIds = $this->integrantesIds();

        $this->presentes = AsistenciaGrupo::query()
            ->where('grupo_id', $this->grupo_id)
            ->whereDate('fecha', $this->fecha)
            ->where('presente', true)
            ->pluck('persona_id')
            ->map(fn ($id): int => (int) $id)
            ->intersect($integrantesIds)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function integrantesIds(): array
    {
        if (! $this->grupo_id) {
            return [];
        }

        return ParticipacionGrupo::query()
            ->where('grupo_id', $this->grupo_id)
            ->when($this->fecha, function (Builder $query): void {
                $query->where(function (Builder $subQuery): void {
                    $subQuery->whereNull('fecha_inicio')
                        ->orWhereDate('fecha_inicio', '<=', $this->fecha);
                })->where(function (Builder $subQuery): void {
                    $subQuery->whereNull('fecha_fin')
                        ->orWhereDate('fecha_fin', '>=', $this->fecha);
                });
            })
            ->pluck('persona_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function integrantesOptions(): array
    {
        $integrantesIds = $this->integrantesIds();

        if ($integrantesIds === []) {
            return [];
        }

        return Persona::query()
            ->whereIn('id', $integrantesIds)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Persona $persona): array => [
                $persona->id => $this->personaLabel($persona),
            ])
            ->all();
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->apellido} {$persona->nombre}");
    }

    protected function grupoOptionLabel(Grupo $grupo): string
    {
        $frecuencia = Grupo::frecuenciasAsistencia()[$grupo->frecuencia_asistencia ?? ''] ?? 'Semanal';

        return "{$grupo->nombre} ({$frecuencia})";
    }

    protected function gruposDeCrecimientoQuery(): Builder
    {
        return Grupo::query()
            ->whereHas('tipoGrupo', fn (Builder $query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%crecimiento%']));
    }
}
