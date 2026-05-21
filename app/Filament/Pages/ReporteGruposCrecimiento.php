<?php

namespace App\Filament\Pages;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReporteGruposCrecimiento extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string | \UnitEnum | null $navigationGroup = 'Asistencia';

    protected static ?string $navigationLabel = 'Reporte grupos de crecimiento';

    protected static ?int $navigationSort = 18;

    protected static ?string $title = 'Reporte grupos de crecimiento';

    protected string $view = 'filament.pages.reporte-grupos-crecimiento';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'coordinador_grupos']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargarPdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('reporte-grupos-crecimiento.pdf'))
                ->openUrlInNewTab(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Grupos de crecimiento')
            ->emptyStateHeading('No hay grupos de crecimiento')
            ->emptyStateDescription('Cuando existan grupos de crecimiento activos, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('participantes_count')
                    ->label('Participantes')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('reuniones_registradas_count')
                    ->label('Reuniones registradas')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('frecuencia_asistencia')
                    ->label('Frecuencia')
                    ->formatStateUsing(fn (?string $state): string => Grupo::frecuenciasAsistencia()[$state] ?? '-')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('promedio_asistencia')
                    ->label('% promedio de asistencia')
                    ->state(fn (Grupo $record): string => $this->formatPromedioAsistencia($record))
                    ->badge()
                    ->color(fn (Grupo $record): string => $this->getPromedioColor($record))
                    ->alignCenter(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $fechaActual = now()->toDateString();

        return Grupo::query()
            ->select('grupos.*')
            ->join('tipo_grupos', 'tipo_grupos.id', '=', 'grupos.tipo_grupo_id')
            ->where('tipo_grupos.nombre', 'Crecimiento')
            ->where('grupos.activo', true)
            ->selectSub(
                ParticipacionGrupo::query()
                    ->selectRaw('COUNT(DISTINCT persona_id)')
                    ->whereColumn('grupo_id', 'grupos.id')
                    ->where(function (Builder $query) use ($fechaActual): void {
                        $query->whereNull('fecha_fin')
                            ->orWhereDate('fecha_fin', '>=', $fechaActual);
                    }),
                'participantes_count'
            )
            ->selectSub(
                AsistenciaGrupo::query()
                    ->selectRaw('COUNT(DISTINCT fecha)')
                    ->whereColumn('grupo_id', 'grupos.id'),
                'reuniones_registradas_count'
            )
            ->selectSub(
                AsistenciaGrupo::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('grupo_id', 'grupos.id')
                    ->where('presente', true),
                'presentes_count'
            );
    }

    protected function formatPromedioAsistencia(Grupo $grupo): string
    {
        return $this->getPromedioAsistencia($grupo).'%';
    }

    protected function getPromedioAsistencia(Grupo $grupo): int
    {
        $participantes = (int) ($grupo->participantes_count ?? 0);
        $reuniones = (int) ($grupo->reuniones_registradas_count ?? 0);

        if ($participantes === 0 || $reuniones === 0) {
            return 0;
        }

        return (int) round(((int) ($grupo->presentes_count ?? 0) / ($participantes * $reuniones)) * 100);
    }

    protected function getPromedioColor(Grupo $grupo): string
    {
        $promedio = $this->getPromedioAsistencia($grupo);

        return match (true) {
            $promedio >= 80 => 'success',
            $promedio >= 50 => 'warning',
            default => 'danger',
        };
    }
}
