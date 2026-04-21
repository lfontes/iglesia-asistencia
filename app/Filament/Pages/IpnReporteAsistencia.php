<?php

namespace App\Filament\Pages;

use App\Models\IpnAsistencia;
use App\Models\IpnAula;
use App\Models\IpnAulaPersona;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IpnReporteAsistencia extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'IPN';

    protected static ?string $navigationLabel = 'Reporte de asistencia';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Reporte de asistencia IPN';

    protected static string $view = 'filament.pages.ipn-reporte-asistencia';

    public ?int $ipn_aula_id = null;

    public ?int $persona_id = null;

    public ?string $desde = null;

    public ?string $hasta = null;

    public function mount(): void
    {
        $this->ipn_aula_id = request()->integer('ipn_aula_id') ?: null;
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

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Filtro')
                ->schema([
                    Forms\Components\Select::make('ipn_aula_id')
                        ->label('Aula')
                        ->placeholder('Selecciona un aula')
                        ->options(fn (): array => IpnAula::query()
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Forms\Components\Select::make('persona_id')
                        ->label('Niño')
                        ->placeholder('Todos')
                        ->options(fn (): array => $this->ninosOptions())
                        ->searchable()
                        ->live(),
                    Forms\Components\DatePicker::make('desde')
                        ->label('Desde')
                        ->live(),
                    Forms\Components\DatePicker::make('hasta')
                        ->label('Hasta')
                        ->live(),
                ])
                ->columns(4),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function ninosOptions(): array
    {
        if (! $this->ipn_aula_id) {
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
        if (! $this->ipn_aula_id) {
            return collect();
        }

        return IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->select('fecha')
            ->distinct()
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(fn ($date): string => (string) Carbon::parse($date)->toDateString())
            ->values();
    }

    public function getSummary(): array
    {
        $rows = $this->getRows();
        $dates = $this->getDates();
        $totalPresentes = $rows->sum('presentes');
        $totalPosibles = $rows->count() * $dates->count();

        return [
            'aula' => $this->ipn_aula_id ? IpnAula::query()->find($this->ipn_aula_id) : null,
            'total_ninos' => $rows->count(),
            'total_fechas' => $dates->count(),
            'total_presentes' => $totalPresentes,
            'promedio' => $totalPosibles > 0 ? (int) round(($totalPresentes / $totalPosibles) * 100) : 0,
        ];
    }

    public function getRows(): Collection
    {
        if (! $this->ipn_aula_id) {
            return collect();
        }

        $dates = $this->getDates();
        $asistencias = IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipn_aula_id)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->get()
            ->groupBy('persona_id');

        return IpnAulaPersona::query()
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
    }
}
