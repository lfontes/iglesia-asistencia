<?php

namespace App\Filament\Pages;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\Persona;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class ResumenAsistenciaGrupos extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string | \UnitEnum | null $navigationGroup = 'Asistencia';

    protected static ?string $navigationLabel = 'Resumen de asistencias';

    protected static ?int $navigationSort = 19;

    protected static ?string $title = 'Resumen de asistencias de grupos';

    protected string $view = 'filament.pages.resumen-asistencia-grupos';

    public ?int $grupo_id = null;

    public ?int $persona_id = null;

    protected ?Collection $attendanceRowsCache = null;

    protected ?Collection $attendanceDatesCache = null;

    protected ?int $attendanceRowsCacheGroupId = null;

    protected ?int $attendanceRowsCachePersonaId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canViewGrupoAttendanceReports() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return static::canAccess() && ! ($user?->isSoloLider());
    }

    public function mount(): void
    {
        $availableGroups = $this->getAvailableGroups();
        $grupoIdDesdeUrl = request()->integer('grupo_id');
        $personaIdDesdeUrl = request()->integer('persona_id');

        if ($grupoIdDesdeUrl && array_key_exists($grupoIdDesdeUrl, $availableGroups)) {
            $this->grupo_id = $grupoIdDesdeUrl;
        }

        if ($personaIdDesdeUrl) {
            $this->persona_id = $personaIdDesdeUrl;
        }

        $this->form->fill([
            'grupo_id' => $this->grupo_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('grupo_id')
                ->label('Grupo')
                ->options(fn (): array => $this->getAvailableGroups())
                ->searchable()
                ->live()
                ->required()
                ->placeholder('Selecciona un grupo'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->emptyStateHeading('Sin datos de asistencia')
            ->emptyStateDescription('Aun no hay participantes o asistencias registradas para este grupo.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Persona')
                    ->state(function (Persona $record): string {
                        $row = $this->getAttendanceRowForPersona((int) $record->id);
                        $nombreCompleto = trim("{$record->nombre} {$record->apellido}");

                        if (! $row) {
                            return $nombreCompleto;
                        }

                        return "{$nombreCompleto} {$row['presentes']}/{$row['ausencias']}";
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('personas.nombre', $direction)
                            ->orderBy('personas.apellido', $direction);
                    })
                    ->weight('medium'),
                TextColumn::make('porcentaje')
                    ->label('%')
                    ->state(function (Persona $record): string {
                        $row = $this->getAttendanceRowForPersona((int) $record->id);

                        return (($row['porcentaje'] ?? 0)).'%';
                    })
                    ->badge()
                    ->color(function (Persona $record): string {
                        $row = $this->getAttendanceRowForPersona((int) $record->id);

                        return $this->getPercentageColor((int) ($row['porcentaje'] ?? 0));
                    })
                    ->alignCenter(),
                ...$this->getAttendanceDateColumns(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tomarAsistencia')
                ->label('Tomar asistencia')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->url(AsistenciaGruposCrecimiento::getUrl()),
        ];
    }

    public function updatedGrupoId(): void
    {
        $this->resetAttendanceCache();
        $this->resetTable();
    }

    public function updatedPersonaId(): void
    {
        $this->resetAttendanceCache();
        $this->resetTable();
    }

    /**
     * @return array<int, string>
     */
    protected function getAvailableGroups(): array
    {
        return Grupo::query()
            ->select('grupos.id', 'grupos.nombre')
            ->join('tipo_grupos', 'tipo_grupos.id', '=', 'grupos.tipo_grupo_id')
            ->where('tipo_grupos.nombre', 'Crecimiento')
            ->where('grupos.activo', true)
            ->orderBy('grupos.nombre')
            ->pluck('grupos.nombre', 'grupos.id')
            ->all();
    }

    /**
     * @return array{grupo:?Grupo,total_personas:int,total_fechas:int,total_presentes:int,promedio_asistencia:int}
     */
    public function getSummaryData(): array
    {
        $grupo = $this->getSelectedGroup();
        $rows = $this->getAttendanceRows();
        $totalFechas = $this->getAttendanceDates()->count();
        $totalPersonas = $rows->count();
        $totalPresentes = $rows->sum('presentes');
        $promedio = $totalPersonas > 0 && $totalFechas > 0
            ? (int) round(($totalPresentes / ($totalPersonas * $totalFechas)) * 100)
            : 0;

        return [
            'grupo' => $grupo,
            'total_personas' => $totalPersonas,
            'total_fechas' => $totalFechas,
            'total_presentes' => $totalPresentes,
            'promedio_asistencia' => $promedio,
        ];
    }

    /**
     * @return Collection<int, array<string, int|string|null>>
     */
    public function getAttendanceRows(): Collection
    {
        $this->primeAttendanceCache();

        return $this->attendanceRowsCache ?? collect();
    }

    public function getFocusedPersonaName(): ?string
    {
        if (! $this->persona_id) {
            return null;
        }

        $row = $this->getAttendanceRows()->firstWhere('persona_id', $this->persona_id);

        return $row['nombre_completo'] ?? null;
    }

    public function getAttendanceDates(): Collection
    {
        if (! $this->grupo_id) {
            return collect();
        }

        if ($this->attendanceDatesCache !== null && $this->attendanceRowsCacheGroupId === $this->grupo_id) {
            return $this->attendanceDatesCache;
        }

        $this->attendanceDatesCache = AsistenciaGrupo::query()
            ->where('grupo_id', $this->grupo_id)
            ->distinct()
            ->orderBy('fecha')
            ->pluck('fecha');

        return $this->attendanceDatesCache;
    }

    public function getTotalFechas(): int
    {
        return $this->getAttendanceDates()->count();
    }

    public function getSelectedGroup(): ?Grupo
    {
        if (! $this->grupo_id) {
            return null;
        }

        return Grupo::query()->find($this->grupo_id);
    }

    protected function getTableQuery(): Builder
    {
        if (! $this->grupo_id) {
            return Persona::query()->whereRaw('1 = 0');
        }

        return Persona::query()
            ->select('personas.*')
            ->join('participacion_grupos', 'participacion_grupos.persona_id', '=', 'personas.id')
            ->where('participacion_grupos.grupo_id', $this->grupo_id)
            ->when($this->persona_id, fn (Builder $query): Builder => $query->where('personas.id', $this->persona_id))
            ->distinct();
    }

    /**
     * @return array<int, IconColumn>
     */
    protected function getAttendanceDateColumns(): array
    {
        return $this->getAttendanceDates()
            ->map(function (string $fecha): IconColumn {
                $url = AsistenciaGruposCrecimiento::getUrl(['grupo_id' => $this->grupo_id, 'fecha' => $fecha]);
                $label = new HtmlString(
                    '<a href="' . e($url) . '" class="hover:underline hover:text-primary-600">'
                    . e(Carbon::parse($fecha)->format('d/m'))
                    . '</a>'
                );

                return IconColumn::make('asistencia_' . str_replace('-', '_', $fecha))
                    ->label($label)
                    ->state(fn (Persona $record): ?bool => $this->getAttendanceStateForPersona((int) $record->id, $fecha))
                    ->icon(fn (?bool $state): string => match ($state) {
                        true => 'heroicon-o-check-circle',
                        false => 'heroicon-o-x-circle',
                        default => 'heroicon-o-minus-circle',
                    })
                    ->color(fn (?bool $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter();
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getAttendanceRowForPersona(int $personaId): ?array
    {
        return $this->getAttendanceRows()->firstWhere('persona_id', $personaId);
    }

    protected function getAttendanceStateForPersona(int $personaId, string $fecha): ?bool
    {
        $row = $this->getAttendanceRowForPersona($personaId);

        return $row['attendance_by_date'][$fecha] ?? null;
    }

    protected function getPercentageColor(int $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'success',
            $percentage >= 50 => 'warning',
            default => 'danger',
        };
    }

    protected function primeAttendanceCache(): void
    {
        if (
            $this->attendanceRowsCache !== null
            && $this->attendanceRowsCacheGroupId === $this->grupo_id
            && $this->attendanceRowsCachePersonaId === $this->persona_id
        ) {
            return;
        }

        if (! $this->grupo_id) {
            $this->attendanceRowsCache = collect();
            $this->attendanceRowsCacheGroupId = $this->grupo_id;
            $this->attendanceRowsCachePersonaId = $this->persona_id;

            return;
        }

        $fechas = $this->getAttendanceDates();
        $totalFechas = $fechas->count();

        $participantes = ParticipacionGrupo::query()
            ->with('persona:id,nombre,apellido')
            ->where('grupo_id', $this->grupo_id)
            ->when($this->persona_id, fn ($query) => $query->where('persona_id', $this->persona_id))
            ->get()
            ->unique('persona_id')
            ->values();

        $agregados = AsistenciaGrupo::query()
            ->selectRaw('persona_id')
            ->selectRaw('SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) AS presentes')
            ->selectRaw('COUNT(*) AS registros')
            ->selectRaw('MAX(CASE WHEN presente = 1 THEN fecha ELSE NULL END) AS ultima_asistencia')
            ->where('grupo_id', $this->grupo_id)
            ->groupBy('persona_id')
            ->get()
            ->keyBy('persona_id');

        $asistenciasPorPersona = AsistenciaGrupo::query()
            ->select(['persona_id', 'fecha', 'presente'])
            ->where('grupo_id', $this->grupo_id)
            ->get()
            ->groupBy('persona_id');

        $this->attendanceRowsCache = $participantes
            ->map(function (ParticipacionGrupo $participacion) use ($agregados, $asistenciasPorPersona, $fechas, $totalFechas): array {
                $persona = $participacion->persona;
                $agregado = $agregados->get($participacion->persona_id);
                $presentes = (int) ($agregado->presentes ?? 0);
                $porcentaje = $totalFechas > 0
                    ? (int) round(($presentes / $totalFechas) * 100)
                    : 0;
                $asistencias = collect($asistenciasPorPersona->get($participacion->persona_id, collect()))
                    ->keyBy(fn (AsistenciaGrupo $asistencia): string => (string) $asistencia->fecha);

                $attendanceByDate = $fechas
                    ->mapWithKeys(function (string $fecha) use ($asistencias): array {
                        $asistencia = $asistencias->get($fecha);

                        return [$fecha => $asistencia ? (bool) $asistencia->presente : null];
                    })
                    ->all();

                return [
                    'persona_id' => (int) $participacion->persona_id,
                    'nombre_completo' => trim(collect([$persona?->nombre, $persona?->apellido])->filter()->implode(' ')),
                    'presentes' => $presentes,
                    'ausencias' => max($totalFechas - $presentes, 0),
                    'porcentaje' => $porcentaje,
                    'ultima_asistencia' => $agregado->ultima_asistencia ?? null,
                    'attendance_by_date' => $attendanceByDate,
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['porcentaje'], $row['presentes'], $row['nombre_completo']])
            ->values();

        $this->attendanceRowsCacheGroupId = $this->grupo_id;
        $this->attendanceRowsCachePersonaId = $this->persona_id;
    }

    protected function resetAttendanceCache(): void
    {
        $this->attendanceRowsCache = null;
        $this->attendanceDatesCache = null;
        $this->attendanceRowsCacheGroupId = null;
        $this->attendanceRowsCachePersonaId = null;
    }
}
