<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\TipoGrupo;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MisGruposMinisteriales extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Liderazgo';

    protected static ?string $navigationLabel = 'Mis grupos ministeriales';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Mis grupos ministeriales';

    protected static ?string $slug = 'mis-grupos-ministeriales';

    protected static string $view = 'filament.pages.mis-grupos-ministeriales';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageLeadershipArea() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('lider') && ! $user->hasRole('admin'));
    }

    /**
     * @return Collection<int, array{
     *   grupo: Grupo,
     *   integrantes_activos:int,
     *   en_crecimiento:int,
     *   sin_crecimiento:int
     * }>
     */
    public function getRows(): Collection
    {
        $user = auth()->user();

        if (! $user?->persona) {
            return collect();
        }

        $growthTypeId = $this->getGrowthTypeId();

        return $user->gruposMinisterialesLiderados()
            ->get()
            ->map(function (Grupo $grupo) use ($growthTypeId): array {
                $integranteIds = ParticipacionGrupo::query()
                    ->where('grupo_id', $grupo->id)
                    ->where(function ($query): void {
                        $query->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                    })
                    ->distinct()
                    ->pluck('persona_id');

                $enCrecimiento = $integranteIds->isEmpty() || ! $growthTypeId
                    ? 0
                    : ParticipacionGrupo::query()
                        ->whereIn('persona_id', $integranteIds)
                        ->whereHas('grupo', fn ($query) => $query->where('tipo_grupo_id', $growthTypeId))
                        ->where(function ($query): void {
                            $query->whereNull('fecha_fin')
                                ->orWhere('fecha_fin', '>=', now()->toDateString());
                        })
                        ->distinct('persona_id')
                        ->count('persona_id');

                return [
                    'grupo' => $grupo,
                    'integrantes_activos' => $integranteIds->count(),
                    'en_crecimiento' => $enCrecimiento,
                    'sin_crecimiento' => max($integranteIds->count() - $enCrecimiento, 0),
                ];
            })
            ->sortBy(fn (array $row) => $row['grupo']->nombre)
            ->values();
    }

    public function getSummaryUrl(Grupo $grupo): string
    {
        return ResumenGrupoMinisterial::getUrl([
            'grupo_id' => $grupo->id,
        ]);
    }

    protected function getGrowthTypeId(): ?int
    {
        return TipoGrupo::query()
            ->whereRaw('LOWER(nombre) = ?', ['crecimiento'])
            ->value('id');
    }
}
