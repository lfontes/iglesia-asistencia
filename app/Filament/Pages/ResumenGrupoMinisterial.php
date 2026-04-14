<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ResumenGrupoMinisterial extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Resumen del grupo ministerial';

    protected static ?string $slug = 'resumen-grupo-ministerial';

    protected static string $view = 'filament.pages.resumen-grupo-ministerial';

    public ?int $grupo_id = null;

    public function mount(): void
    {
        $this->grupo_id = request()->integer('grupo_id');

        abort_unless($this->grupo_id, 404);
        abort_unless($this->getGrupo(), 404);
        abort_unless($this->canViewGroup(), 403);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageLeadershipArea() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver a mis grupos')
                ->url(MisGruposMinisteriales::getUrl())
                ->color('gray'),
        ];
    }

    public function getGrupo(): ?Grupo
    {
        if (! $this->grupo_id) {
            return null;
        }

        return Grupo::query()
            ->with('tipoGrupo:id,nombre')
            ->find($this->grupo_id);
    }

    /**
     * @return array{total_personas:int,en_crecimiento:int,sin_crecimiento:int}
     */
    public function getSummary(): array
    {
        $rows = $this->getPeopleRows();

        return [
            'total_personas' => $rows->count(),
            'en_crecimiento' => $rows->where('en_crecimiento', true)->count(),
            'sin_crecimiento' => $rows->where('en_crecimiento', false)->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *   persona_id:int,
     *   nombre:string,
     *   telefono:?string,
     *   en_crecimiento:bool,
     *   grupos_crecimiento:string,
     *   primer_grupo_crecimiento_id:?int
     * }>
     */
    public function getPeopleRows(): Collection
    {
        if (! $this->grupo_id) {
            return collect();
        }

        $tipoCrecimientoId = $this->getGrowthTypeId();

        return Persona::query()
            ->whereHas('participacionesGrupo', function ($query): void {
                $query->where('grupo_id', $this->grupo_id)
                    ->where(function ($subQuery): void {
                        $subQuery->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                    });
            })
            ->with([
                'participacionesGrupo' => function ($query) use ($tipoCrecimientoId): void {
                    $query->with('grupo:id,nombre,tipo_grupo_id')
                        ->where(function ($subQuery) use ($tipoCrecimientoId): void {
                            $subQuery->where('grupo_id', $this->grupo_id);

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
            ->map(function (Persona $persona) use ($tipoCrecimientoId): array {
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
                    'en_crecimiento' => $growthGroups->isNotEmpty(),
                    'grupos_crecimiento' => $growthGroups->pluck('nombre')->implode(', '),
                    'primer_grupo_crecimiento_id' => $growthGroups->first()['id'] ?? null,
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

    protected function canViewGroup(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return (bool) $user->persona?->lideraGrupoMinisterial((int) $this->grupo_id);
    }

    protected function getGrowthTypeId(): ?int
    {
        return TipoGrupo::query()
            ->whereRaw('LOWER(nombre) = ?', ['crecimiento'])
            ->value('id');
    }
}
