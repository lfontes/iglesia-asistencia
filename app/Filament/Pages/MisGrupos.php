<?php

namespace App\Filament\Pages;

use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\GrupoResource;
use App\Models\Grupo;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MisGrupos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static string | \UnitEnum | null $navigationGroup = 'Liderazgo';

    protected static ?string $navigationLabel = 'Mis grupos';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Mis grupos';

    protected static ?string $slug = 'mis-grupos';

    protected string $view = 'filament.pages.mis-grupos';

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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Grupos que administras')
            ->description('Aquí puedes crear grupos nuevos y administrar sólo los grupos que te corresponden.')
            ->emptyStateHeading('Aún no tienes grupos propios para administrar')
            ->emptyStateDescription('Cuando crees o te asignen grupos, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('tipoGrupo.nombre')
                    ->label('Tipo')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('lider_nombre_completo')
                    ->label('Líder')
                    ->state(fn (Grupo $record): string => $record->lider ? trim($record->lider->apellido.' '.$record->lider->nombre) : '-')
                    ->placeholder('-'),
                TextColumn::make('integrantes_activos')
                    ->label('Integrantes activos')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('anio')
                    ->label('Año')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Grupo $record): string => $this->getEditUrl($record)),
                Action::make('participantes')
                    ->label('Participantes')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->url(fn (Grupo $record): string => $this->getParticipacionUrl($record)),
            ]);
    }

    public function getEditUrl(Grupo $grupo): string
    {
        return GrupoResource::getUrl('edit', ['record' => $grupo]);
    }

    public function getParticipacionUrl(Grupo $grupo): string
    {
        return GrupoResource::getUrl('participacion', ['record' => $grupo]);
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return Grupo::query()->whereRaw('1 = 0');
        }

        return $user->misGruposQuery()->addSelect([
            'integrantes_activos' => DB::table('participacion_grupos')
                ->selectRaw('COUNT(DISTINCT persona_id)')
                ->whereColumn('participacion_grupos.grupo_id', 'grupos.id')
                ->where(function ($query): void {
                    $query->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', now()->toDateString());
                }),
        ]);
    }
}
