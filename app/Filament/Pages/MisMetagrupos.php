<?php

namespace App\Filament\Pages;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Filament\Resources\MetagrupoResource;
use App\Models\Metagrupo;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MisMetagrupos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string | \UnitEnum | null $navigationGroup = 'Liderazgo';

    protected static ?string $navigationLabel = 'Mis metagrupos';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Mis metagrupos';

    protected static ?string $slug = 'mis-metagrupos';

    protected string $view = 'filament.pages.mis-metagrupos';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageLeadershipArea() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('lider') && ! $user->hasRole('admin'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Metagrupos asignados')
            ->description('Accede sólo a los metagrupos que lideras para seguir a tus equipos.')
            ->emptyStateHeading('No tienes metagrupos asignados todavía')
            ->emptyStateDescription('Cuando se te asignen metagrupos, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-rectangle-group')
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Metagrupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('lider_nombre_completo')
                    ->label('Líder')
                    ->state(fn (Metagrupo $record): string => $record->lider ? trim($record->lider->apellido.' '.$record->lider->nombre) : '-')
                    ->placeholder('-'),
                TextColumn::make('grupos_count')
                    ->label('Grupos')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('personas_count')
                    ->label('Personas')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
            ])
            ->recordActions([
                Action::make('verDetalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Metagrupo $record): string => $this->getViewUrl($record)),
            ]);
    }

    public function getViewUrl(Metagrupo $metagrupo): string
    {
        return MetagrupoResource::getUrl('view', ['record' => $metagrupo]);
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return Metagrupo::query()->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin')) {
            return Metagrupo::query()
                ->with(['lider:id,nombre,apellido'])
                ->withSummaryColumns()
                ->orderBy('nombre');
        }

        return $user->metagruposLiderados()
            ->with(['lider:id,nombre,apellido']);
    }
}
