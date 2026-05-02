<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Ipn;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use App\Models\IpnAsistencia;
use App\Models\IpnAulaPersona;
use App\Models\Persona;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IpnTomarAsistencia extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = Ipn::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Tomar asistencia';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Tomar asistencia IPN';

    protected string $view = 'filament.pages.ipn-tomar-asistencia';

    public ?int $ipn_aula_id = null;

    public ?string $fecha = null;

    /** @var array<int, int|string> */
    public array $presentes = [];

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $aulaIdDesdeUrl = request()->integer('ipn_aula_id') ?: null;
        $this->ipn_aula_id = $aulaIdDesdeUrl && array_key_exists($aulaIdDesdeUrl, $this->aulasOptions())
            ? $aulaIdDesdeUrl
            : null;

        $this->refrescarAsistenciaCargada();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canTakeIpnAttendance();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtro')
                ->schema([
                    Select::make('ipn_aula_id')
                        ->label('Aula')
                        ->placeholder('Selecciona un aula')
                        ->options(fn (): array => $this->aulasOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn (): mixed => $this->refrescarAsistenciaCargada()),
                    DatePicker::make('fecha')
                        ->label('Fecha')
                        ->default(now()->toDateString())
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn (): mixed => $this->refrescarAsistenciaCargada()),
                ])
                ->columns(2),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agregarNino')
                ->label('Agregar niño al aula')
                ->icon('heroicon-o-user-plus')
                ->disabled(fn (): bool => blank($this->ipn_aula_id))
                ->schema([
                    Select::make('persona_id')
                        ->label('Niño existente')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Persona::query()
                            ->where('es_menor', true)
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
                            $persona = $value ? Persona::query()->find($value) : null;

                            return $persona ? $this->personaLabel($persona) : null;
                        }),
                    TextInput::make('nombre')
                        ->label('Nombre nuevo')
                        ->maxLength(255),
                    TextInput::make('apellido')
                        ->label('Apellido nuevo')
                        ->maxLength(255),
                    DatePicker::make('fecha_nacimiento')
                        ->label('Fecha de nacimiento')
                        ->native(false),
                    Select::make('responsable_persona_id')
                        ->label('Responsable / tutor')
                        ->searchable()
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
                            $persona = $value ? Persona::query()->find($value) : null;

                            return $persona ? $this->personaLabel($persona) : null;
                        }),
                ])
                ->action(fn (array $data): mixed => $this->agregarNinoAlAula($data)),
        ];
    }

    public function guardar(): void
    {
        $state = $this->form->getState();
        $aulaId = isset($state['ipn_aula_id']) ? (int) $state['ipn_aula_id'] : null;
        $fecha = $state['fecha'] ?? null;

        if (! $aulaId || ! $fecha) {
            Notification::make()
                ->title('Debe seleccionar un aula y una fecha')
                ->danger()
                ->send();

            return;
        }

        if (! array_key_exists($aulaId, $this->aulasOptions())) {
            Notification::make()
                ->title('No tienes acceso a esta aula')
                ->danger()
                ->send();

            return;
        }

        $ninos = $this->ninosActivos();
        $presentesIds = collect($this->presentes)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique();

        foreach ($ninos as $nino) {
            IpnAsistencia::updateOrCreate(
                [
                    'ipn_aula_id' => $aulaId,
                    'persona_id' => $nino['id'],
                    'fecha' => $fecha,
                ],
                [
                    'presente' => $presentesIds->contains((int) $nino['id']),
                    'created_by' => auth()->id(),
                ]
            );
        }

        $this->refrescarAsistenciaCargada();

        Notification::make()
            ->title('Asistencia IPN guardada correctamente')
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, array{id:int,label:string,edad:?int,responsable:?string}>
     */
    public function ninosActivos(): Collection
    {
        if (! $this->ipn_aula_id) {
            return collect();
        }

        if (! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            return collect();
        }

        $fecha = $this->fecha ?: now()->toDateString();

        return IpnAulaPersona::query()
            ->with('persona.responsablePersona:id,nombre,apellido,telefono')
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->where('activo', true)
            ->where(function (Builder $query) use ($fecha): void {
                $query->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fecha);
            })
            ->where(function (Builder $query) use ($fecha): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fecha);
            })
            ->get()
            ->filter(fn (IpnAulaPersona $participacion): bool => $participacion->persona !== null)
            ->map(fn (IpnAulaPersona $participacion): array => [
                'id' => (int) $participacion->persona_id,
                'label' => $this->personaNombreCompleto($participacion->persona),
                'edad' => $participacion->persona->edad,
                'responsable' => $participacion->persona->responsableIpnLabel(),
            ])
            ->sortBy('label')
            ->values();
    }

    protected function refrescarAsistenciaCargada(): void
    {
        if (! $this->ipn_aula_id || ! $this->fecha) {
            $this->presentes = [];

            return;
        }

        if (! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            $this->presentes = [];

            return;
        }

        $this->presentes = IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->whereDate('fecha', $this->fecha)
            ->where('presente', true)
            ->pluck('persona_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    protected function agregarNinoAlAula(array $data): void
    {
        if (! $this->ipn_aula_id) {
            return;
        }

        if (! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            Notification::make()
                ->title('No tienes acceso a esta aula')
                ->danger()
                ->send();

            return;
        }

        $personaId = filled($data['persona_id'] ?? null) ? (int) $data['persona_id'] : null;

        if (! $personaId) {
            $nombre = trim((string) ($data['nombre'] ?? ''));
            $apellido = trim((string) ($data['apellido'] ?? ''));

            if ($nombre === '' || $apellido === '') {
                Notification::make()
                    ->title('Selecciona un niño existente o ingresa nombre y apellido')
                    ->danger()
                    ->send();

                return;
            }

            $persona = Persona::create([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'responsable_persona_id' => $data['responsable_persona_id'] ?? null,
                'es_menor' => true,
            ]);

            $personaId = (int) $persona->id;
        }

        IpnAulaPersona::firstOrCreate(
            [
                'ipn_aula_id' => $this->ipn_aula_id,
                'persona_id' => $personaId,
                'activo' => true,
            ],
            [
                'fecha_inicio' => $this->fecha ?: now()->toDateString(),
            ]
        );

        Notification::make()
            ->title('Niño agregado al aula')
            ->success()
            ->send();
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->id} - {$persona->apellido} {$persona->nombre}");
    }

    /**
     * @return array<int, string>
     */
    protected function aulasOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $user->ipnAulasDisponibles()
            ->activas()
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    protected function personaNombreCompleto(Persona $persona): string
    {
        return trim("{$persona->apellido} {$persona->nombre}");
    }
}
