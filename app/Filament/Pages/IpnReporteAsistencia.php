<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Ipn;
use App\Models\IpnAsistencia;
use App\Models\IpnAula;
use App\Models\IpnAulaPersona;
use App\Models\Persona;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IpnReporteAsistencia extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Ipn::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Reporte de asistencia';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Reporte de asistencia IPN';

    protected string $view = 'filament.pages.ipn-reporte-asistencia';

    public ?int $ipn_aula_id = null;

    public ?int $persona_id = null;

    public ?string $desde = null;

    public ?string $hasta = null;

    protected ?Collection $reportRowsCache = null;

    protected ?Collection $reportDatesCache = null;

    protected ?string $reportCacheKey = null;

    public function mount(): void
    {
        $aulaIdDesdeUrl = request()->integer('ipn_aula_id') ?: null;
        $this->ipn_aula_id = $aulaIdDesdeUrl && array_key_exists($aulaIdDesdeUrl, $this->aulasOptions())
            ? $aulaIdDesdeUrl
            : null;
        $this->desde = now()->subMonths(2)->toDateString();
        $this->hasta = now()->toDateString();

        $this->form->fill([
            'ipn_aula_id' => $this->ipn_aula_id,
            'persona_id' => $this->persona_id,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ]);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAccessIpn();
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
                        ->live(),
                    Select::make('persona_id')
                        ->label('Niño')
                        ->placeholder('Todos')
                        ->options(fn (): array => $this->ninosOptions())
                        ->searchable()
                        ->live(),
                    DatePicker::make('desde')
                        ->label('Desde')
                        ->live(),
                    DatePicker::make('hasta')
                        ->label('Hasta')
                        ->live(),
                ])
                ->columns(4),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->emptyStateHeading('Sin datos de asistencia')
            ->emptyStateDescription('Selecciona un aula o registra asistencia IPN para ver el reporte.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Niño')
                    ->state(function (Persona $record): string {
                        $row = $this->getRowForPersona((int) $record->id);
                        $nombreCompleto = trim("{$record->apellido} {$record->nombre}");

                        if (! $row) {
                            return $nombreCompleto;
                        }

                        return $nombreCompleto;
                    })
                    ->description(function (Persona $record): string {
                        $row = $this->getRowForPersona((int) $record->id);

                        if (! $row) {
                            return 'Sin detalles';
                        }

                        $edad = $row['edad'] !== null ? $row['edad'].' años' : 'Edad sin cargar';

                        return $edad.' · '.($row['responsable'] ?: 'Sin responsable');
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('personas.apellido', $direction)
                            ->orderBy('personas.nombre', $direction);
                    })
                    ->weight('medium'),
                TextColumn::make('id')
                    ->label('ID')
                    ->alignCenter(),
                TextColumn::make('porcentaje')
                    ->label('%')
                    ->state(function (Persona $record): string {
                        $row = $this->getRowForPersona((int) $record->id);

                        return (($row['porcentaje'] ?? 0)).'%';
                    })
                    ->badge()
                    ->color(function (Persona $record): string {
                        $row = $this->getRowForPersona((int) $record->id);

                        return $this->getPercentageColor((int) ($row['porcentaje'] ?? 0));
                    })
                    ->alignCenter(),
                ...$this->getAttendanceDateColumns(),
            ]);
    }

    public function updatedIpnAulaId(): void
    {
        $this->resetReportCache();
        $this->resetTable();
    }

    public function updatedPersonaId(): void
    {
        $this->resetReportCache();
        $this->resetTable();
    }

    public function updatedDesde(): void
    {
        $this->resetReportCache();
        $this->resetTable();
    }

    public function updatedHasta(): void
    {
        $this->resetReportCache();
        $this->resetTable();
    }

    /**
     * @return array<int, string>
     */
    protected function ninosOptions(): array
    {
        if (! $this->ipn_aula_id) {
            return [];
        }

        if (! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            return [];
        }

        return Persona::query()
            ->whereHas('ipnParticipaciones', fn ($query) => $query->where('ipn_aula_id', $this->ipn_aula_id))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Persona $persona): array => [
                $persona->id => trim("{$persona->id} - {$persona->apellido} {$persona->nombre}"),
            ])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public function getDates(): Collection
    {
        if ($this->reportDatesCache !== null && $this->reportCacheKey === $this->makeReportCacheKey()) {
            return $this->reportDatesCache;
        }

        if (! $this->ipn_aula_id || ! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            return collect();
        }

        $this->reportDatesCache = IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->select('fecha')
            ->distinct()
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(fn ($date): string => (string) Carbon::parse($date)->toDateString())
            ->values();

        return $this->reportDatesCache;
    }

    public function getSummary(): array
    {
        $rows = $this->getRows();
        $dates = $this->getDates();
        $totalPresentes = $rows->sum('presentes');
        $totalPosibles = $rows->count() * $dates->count();

        return [
            'aula' => $this->ipn_aula_id && array_key_exists($this->ipn_aula_id, $this->aulasOptions())
                ? IpnAula::query()->find($this->ipn_aula_id)
                : null,
            'total_ninos' => $rows->count(),
            'total_fechas' => $dates->count(),
            'total_presentes' => $totalPresentes,
            'promedio' => $totalPosibles > 0 ? (int) round(($totalPresentes / $totalPosibles) * 100) : 0,
        ];
    }

    public function getRows(): Collection
    {
        $this->primeReportCache();

        return $this->reportRowsCache ?? collect();
    }

    protected function getTableQuery(): Builder
    {
        if (! $this->ipn_aula_id || ! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            return Persona::query()->whereRaw('1 = 0');
        }

        return Persona::query()
            ->select('personas.*')
            ->join('ipn_aula_persona', 'ipn_aula_persona.persona_id', '=', 'personas.id')
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->when($this->persona_id, fn (Builder $query): Builder => $query->where('personas.id', $this->persona_id))
            ->distinct();
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
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * @return array<int, IconColumn>
     */
    protected function getAttendanceDateColumns(): array
    {
        return $this->getDates()
            ->map(function (string $date): IconColumn {
                return IconColumn::make('asistencia_' . str_replace('-', '_', $date))
                    ->label(Carbon::parse($date)->format('d/m'))
                    ->state(fn (Persona $record): ?bool => $this->getAttendanceStateForPersona((int) $record->id, $date))
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
    protected function getRowForPersona(int $personaId): ?array
    {
        return $this->getRows()->firstWhere('persona_id', $personaId);
    }

    protected function getAttendanceStateForPersona(int $personaId, string $date): ?bool
    {
        $row = $this->getRowForPersona($personaId);

        return $row['attendance_by_date'][$date] ?? null;
    }

    protected function getPercentageColor(int $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'success',
            $percentage >= 50 => 'warning',
            default => 'danger',
        };
    }

    protected function primeReportCache(): void
    {
        $cacheKey = $this->makeReportCacheKey();

        if ($this->reportRowsCache !== null && $this->reportCacheKey === $cacheKey) {
            return;
        }

        if (! $this->ipn_aula_id || ! array_key_exists($this->ipn_aula_id, $this->aulasOptions())) {
            $this->reportRowsCache = collect();
            $this->reportDatesCache = collect();
            $this->reportCacheKey = $cacheKey;

            return;
        }

        $dates = $this->getDates();
        $asistencias = IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->get()
            ->groupBy('persona_id');

        $this->reportRowsCache = IpnAulaPersona::query()
            ->with('persona.responsablePersona:id,nombre,apellido,telefono')
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->when($this->persona_id, fn ($query) => $query->where('persona_id', $this->persona_id))
            ->get()
            ->unique('persona_id')
            ->filter(fn (IpnAulaPersona $participacion): bool => $participacion->persona !== null)
            ->map(function (IpnAulaPersona $participacion) use ($asistencias, $dates): array {
                $persona = $participacion->persona;
                $asistenciasPersona = collect($asistencias->get($participacion->persona_id, collect()))
                    ->keyBy(fn (IpnAsistencia $asistencia): string => $asistencia->fecha->toDateString());
                $presentes = $asistenciasPersona->where('presente', true)->count();
                $porcentaje = $dates->count() > 0 ? (int) round(($presentes / $dates->count()) * 100) : 0;

                return [
                    'persona_id' => (int) $persona->id,
                    'nombre_completo' => trim("{$persona->apellido} {$persona->nombre}"),
                    'edad' => $persona->edad,
                    'responsable' => $persona->responsableIpnLabel(),
                    'presentes' => $presentes,
                    'porcentaje' => $porcentaje,
                    'attendance_by_date' => $dates
                        ->mapWithKeys(function (string $date) use ($asistenciasPersona): array {
                            $asistencia = $asistenciasPersona->get($date);

                            return [$date => $asistencia ? (bool) $asistencia->presente : null];
                        })
                        ->all(),
                ];
            })
            ->sortBy('nombre_completo')
            ->values();

        $this->reportCacheKey = $cacheKey;
    }

    protected function resetReportCache(): void
    {
        $this->reportRowsCache = null;
        $this->reportDatesCache = null;
        $this->reportCacheKey = null;
    }

    protected function makeReportCacheKey(): string
    {
        return implode('|', [
            (string) ($this->ipn_aula_id ?? ''),
            (string) ($this->persona_id ?? ''),
            (string) ($this->desde ?? ''),
            (string) ($this->hasta ?? ''),
        ]);
    }
}
