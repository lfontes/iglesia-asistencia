<?php

namespace App\Filament\Resources\MetagrupoResource\Pages;

use App\Filament\Resources\MetagrupoResource;
use App\Models\AsistenciaGrupo;
use App\Filament\Pages\ResumenAsistenciaGrupos;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class ViewMetagrupo extends ViewRecord
{
    protected static string $resource = MetagrupoResource::class;

    protected static string $view = 'filament.resources.metagrupo-resource.pages.view-metagrupo';

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

        return [Actions\EditAction::make()];
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
        if (! $row['en_crecimiento'] || ! $row['primer_grupo_crecimiento_id']) {
            return null;
        }

        return ResumenAsistenciaGrupos::getUrl([
            'grupo_id' => $row['primer_grupo_crecimiento_id'],
            'persona_id' => $row['persona_id'],
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
