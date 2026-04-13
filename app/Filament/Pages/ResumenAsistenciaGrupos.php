<?php

namespace App\Filament\Pages;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ResumenAsistenciaGrupos extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Asistencia';

    protected static ?string $navigationLabel = 'Resumen de asistencias';

    protected static ?int $navigationSort = 19;

    protected static ?string $title = 'Resumen de asistencias de grupos';

    protected static string $view = 'filament.pages.resumen-asistencia-grupos';

    public ?int $grupo_id = null;

    public ?int $persona_id = null;

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
            'grupo_id' => $this->grupo_id ?? array_key_first($availableGroups),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('grupo_id')
                ->label('Grupo')
                ->options(fn (): array => $this->getAvailableGroups())
                ->searchable()
                ->live()
                ->required()
                ->placeholder('Selecciona un grupo'),
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
        if (! $this->grupo_id) {
            return collect();
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

        return $participantes
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

        return AsistenciaGrupo::query()
            ->where('grupo_id', $this->grupo_id)
            ->distinct()
            ->orderBy('fecha')
            ->pluck('fecha');
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
}
