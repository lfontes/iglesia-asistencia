<?php

namespace App\Filament\Resources\MetagrupoResource\Pages;

use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Filament\Pages\ResumenAsistenciaGrupos;
use App\Filament\Resources\MetagrupoResource;
use App\Models\AsistenciaGrupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class ViewMetagrupo extends ViewRecord
{
    protected static string $resource = MetagrupoResource::class;

    /**
     * @throws AuthorizationException
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless(MetagrupoResource::canView($this->record), 403);
    }

    protected function getHeaderActions(): array
    {
        if (! auth()->user()?->hasRole('admin')) {
            return [];
        }

        return [EditAction::make()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Resumen')
                    ->icon('heroicon-o-chart-bar')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_grupos')
                            ->label('Grupos')
                            ->state(fn (): int => $this->getSummary()['total_grupos'])
                            ->badge(),
                        TextEntry::make('total_personas')
                            ->label('Personas únicas')
                            ->state(fn (): int => $this->getSummary()['total_personas'])
                            ->badge(),
                        TextEntry::make('en_crecimiento')
                            ->label('En crecimiento')
                            ->state(fn (): int => $this->getSummary()['en_crecimiento'])
                            ->badge()
                            ->color('success'),
                        TextEntry::make('sin_crecimiento')
                            ->label('Sin crecimiento')
                            ->state(fn (): int => $this->getSummary()['sin_crecimiento'])
                            ->badge()
                            ->color('warning'),
                    ])
                    ->columns(4),

                Section::make('Datos del metagrupo')
                    ->icon('heroicon-o-rectangle-group')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('nombre')
                            ->label('Metagrupo')
                            ->state(fn (): string => (string) $this->record->nombre)
                            ->weight('bold')
                            ->columnSpan(2),
                        TextEntry::make('lider_nombre')
                            ->label('Líder')
                            ->state(fn (): string => $this->record->lider ? trim($this->record->lider->apellido.' '.$this->record->lider->nombre) : 'Sin asignar'),
                        TextEntry::make('activo')
                            ->label('Estado')
                            ->state(fn (): string => $this->record->activo ? 'Activo' : 'Inactivo')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Activo' ? 'success' : 'gray'),
                        TextEntry::make('grupos_incluidos')
                            ->label('Grupos incluidos')
                            ->state(fn (): ?string => ($value = $this->record->grupos->pluck('nombre')->implode(', ')) !== '' ? $value : null)
                            ->default('Sin grupos')
                            ->columnSpanFull(),
                        TextEntry::make('descripcion')
                            ->label('Descripción')
                            ->state(fn (): ?string => filled($this->record->descripcion) ? $this->record->descripcion : null)
                            ->default('Sin descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Personas del metagrupo')
                    ->icon('heroicon-o-users')
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->getPeopleRows()->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('personas')
                            ->hiddenLabel()
                            ->state(fn (): array => $this->getPeopleRows()->all())
                            ->schema([
                                TextEntry::make('nombre')
                                    ->label('Persona')
                                    ->weight('medium')
                                    ->html()
                                    ->formatStateUsing(function (TextEntry $component, string $state): HtmlString {
                                        $url = $this->getGrowthAttendanceUrl($component->getContainer()->getState());

                                        if (! filled($url)) {
                                            return new HtmlString(e($state));
                                        }

                                        return new HtmlString(sprintf(
                                            '<a href="%s" class="text-primary-600 hover:underline">%s</a>',
                                            e($url),
                                            e($state),
                                        ));
                                    }),
                                TextEntry::make('telefono')
                                    ->label('Teléfono')
                                    ->default('-'),
                                TextEntry::make('grupos_metagrupo')
                                    ->label('Grupos del metagrupo')
                                    ->default('-'),
                                TextEntry::make('en_crecimiento')
                                    ->label('Crecimiento')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Sí' ? 'success' : 'warning'),
                                TextEntry::make('porcentaje_asistencia_crecimiento')
                                    ->label('% asistencia')
                                    ->formatStateUsing(fn ($state): ?string => $state !== null && $state !== '' ? $state.'%' : null)
                                    ->default('-'),
                                TextEntry::make('grupos_crecimiento')
                                    ->label('Grupo(s) de crecimiento')
                                    ->default('-'),
                            ])
                            ->columns(6),
                    ]),

                Section::make('Personas del metagrupo')
                    ->icon('heroicon-o-users')
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->getPeopleRows()->isEmpty())
                    ->schema([
                        TextEntry::make('sin_personas')
                            ->hiddenLabel()
                            ->state('Este metagrupo todavía no tiene personas activas en sus grupos.')
                            ->color('gray'),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * @return array{total_grupos:int,total_personas:int,en_crecimiento:int,sin_crecimiento:int}
     */
    public function getSummary(): array
    {
        $people = $this->getPeopleRows();

        return [
            'total_grupos' => $this->record->grupos->count(),
            'total_personas' => $people->count(),
            'en_crecimiento' => $people->where('en_crecimiento', true)->count(),
            'sin_crecimiento' => $people->where('en_crecimiento', false)->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *   persona_id:int,
     *   nombre:string,
     *   telefono:?string,
     *   grupos_metagrupo:string,
     *   en_crecimiento:bool,
     *   grupos_crecimiento:string,
     *   primer_grupo_crecimiento_id:?int,
     *   porcentaje_asistencia_crecimiento:?int
     * }>
     */
    public function getPeopleRows(): Collection
    {
        $metagrupoGroupIds = $this->record->grupos->pluck('id');

        if ($metagrupoGroupIds->isEmpty()) {
            return collect();
        }

        $tipoCrecimientoId = TipoGrupo::query()
            ->whereRaw('LOWER(nombre) = ?', ['crecimiento'])
            ->value('id');

        return Persona::query()
            ->whereHas('participacionesGrupo', function ($query) use ($metagrupoGroupIds): void {
                $query->whereIn('grupo_id', $metagrupoGroupIds)
                    ->where(function ($subQuery): void {
                        $subQuery->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                    });
            })
            ->with([
                'participacionesGrupo' => function ($query) use ($metagrupoGroupIds, $tipoCrecimientoId): void {
                    $query->with('grupo:id,nombre,tipo_grupo_id')
                        ->where(function ($subQuery) use ($metagrupoGroupIds, $tipoCrecimientoId): void {
                            $subQuery->whereIn('grupo_id', $metagrupoGroupIds);

                            if ($tipoCrecimientoId) {
                                $subQuery->orWhereHas('grupo', fn ($groupQuery) => $groupQuery->where('tipo_grupo_id', $tipoCrecimientoId));
                            }
                        })
                        ->where(function ($subQuery): void {
                            $subQuery->whereNull('fecha_fin')
                                ->orWhere('fecha_fin', '>=', now()->toDateString());
                        });
                },
            ])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get()
            ->map(function (Persona $persona) use ($metagrupoGroupIds, $tipoCrecimientoId): array {
                $metagrupoGroups = $persona->participacionesGrupo
                    ->filter(fn ($participacion): bool => $metagrupoGroupIds->contains($participacion->grupo_id))
                    ->pluck('grupo.nombre')
                    ->unique()
                    ->values();

                $growthGroups = $persona->participacionesGrupo
                    ->filter(fn ($participacion): bool => $tipoCrecimientoId !== null && (int) optional($participacion->grupo)->tipo_grupo_id === (int) $tipoCrecimientoId)
                    ->map(fn ($participacion): array => [
                        'id' => (int) $participacion->grupo_id,
                        'nombre' => (string) optional($participacion->grupo)->nombre,
                    ])
                    ->unique('id')
                    ->values();

                return [
                    'persona_id' => $persona->id,
                    'nombre' => trim($persona->apellido.' '.$persona->nombre),
                    'telefono' => $persona->telefono,
                    'grupos_metagrupo' => $metagrupoGroups->implode(', '),
                    'en_crecimiento' => $growthGroups->isNotEmpty(),
                    'grupos_crecimiento' => $growthGroups->pluck('nombre')->implode(', '),
                    'primer_grupo_crecimiento_id' => $growthGroups->first()['id'] ?? null,
                    'porcentaje_asistencia_crecimiento' => $this->resolveGrowthAttendancePercentage(
                        $persona->id,
                        $growthGroups->first()['id'] ?? null,
                    ),
                ];
            })
            ->values();
    }

    public function getGrowthAttendanceUrl(array $row): ?string
    {
        $enCrecimiento = (bool) data_get($row, 'en_crecimiento', false);
        $primerGrupoCrecimientoId = data_get($row, 'primer_grupo_crecimiento_id');
        $personaId = data_get($row, 'persona_id');

        if (! $enCrecimiento || ! $primerGrupoCrecimientoId || ! $personaId) {
            return null;
        }

        return ResumenAsistenciaGrupos::getUrl([
            'grupo_id' => $primerGrupoCrecimientoId,
            'persona_id' => $personaId,
        ]);
    }

    protected function resolveGrowthAttendancePercentage(int $personaId, ?int $grupoId): ?int
    {
        if (! $grupoId) {
            return null;
        }

        $totalFechas = AsistenciaGrupo::query()
            ->where('grupo_id', $grupoId)
            ->distinct('fecha')
            ->count('fecha');

        if ($totalFechas === 0) {
            return null;
        }

        $presentes = AsistenciaGrupo::query()
            ->where('grupo_id', $grupoId)
            ->where('persona_id', $personaId)
            ->where('presente', true)
            ->count();

        return (int) round(($presentes / $totalFechas) * 100);
    }
}
