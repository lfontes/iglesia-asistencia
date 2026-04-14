<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MetagrupoResource;
use App\Models\Metagrupo;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MisMetagrupos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Liderazgo';

    protected static ?string $navigationLabel = 'Mis metagrupos';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Mis metagrupos';

    protected static ?string $slug = 'mis-metagrupos';

    protected static string $view = 'filament.pages.mis-metagrupos';

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
     * @return Collection<int, Metagrupo>
     */
    public function getRows(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        if ($user->hasRole('admin')) {
            return Metagrupo::query()
                ->with(['lider:id,nombre,apellido'])
                ->withSummaryColumns()
                ->orderBy('nombre')
                ->get();
        }

        return $user->metagruposLiderados()
            ->with(['lider:id,nombre,apellido'])
            ->get();
    }

    public function getViewUrl(Metagrupo $metagrupo): string
    {
        return MetagrupoResource::getUrl('view', ['record' => $metagrupo]);
    }
}
