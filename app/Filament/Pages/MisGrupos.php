<?php

namespace App\Filament\Pages;

use App\Filament\Resources\GrupoResource;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MisGrupos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Liderazgo';

    protected static ?string $navigationLabel = 'Mis grupos';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Mis grupos';

    protected static ?string $slug = 'mis-grupos';

    protected static string $view = 'filament.pages.mis-grupos';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('lider');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('lider') && ! $user->hasRole('admin'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('crearGrupo')
                ->label('Crear grupo')
                ->icon('heroicon-o-plus')
                ->url(GrupoResource::getUrl('create')),
        ];
    }

    /**
     * @return Collection<int, array{
     *   grupo: Grupo,
     *   integrantes_activos:int
     * }>
     */
    public function getRows(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return $user->misGruposQuery()
            ->get()
            ->map(function (Grupo $grupo): array {
                $integrantesActivos = ParticipacionGrupo::query()
                    ->where('grupo_id', $grupo->id)
                    ->where(function ($query): void {
                        $query->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                    })
                    ->distinct('persona_id')
                    ->count('persona_id');

                return [
                    'grupo' => $grupo,
                    'integrantes_activos' => $integrantesActivos,
                ];
            })
            ->values();
    }

    public function getEditUrl(Grupo $grupo): string
    {
        return GrupoResource::getUrl('edit', ['record' => $grupo]);
    }

    public function getParticipacionUrl(Grupo $grupo): string
    {
        return GrupoResource::getUrl('participacion', ['record' => $grupo]);
    }
}
