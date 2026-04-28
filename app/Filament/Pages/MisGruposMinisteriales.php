<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
use App\Models\TipoGrupo;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MisGruposMinisteriales extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Liderazgo';

    protected static ?string $navigationLabel = 'Asistencia a grupos';

    protected static ?int $navigationSort = 99;

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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Grupos ministeriales liderados')
            ->description('Aquí puedes seguir qué integrantes de tus grupos ya están en crecimiento.')
            ->emptyStateHeading('No tienes grupos ministeriales asignados todavía')
            ->emptyStateDescription('Cuando se te asignen grupos ministeriales, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('tipoGrupo.nombre')
                    ->label('Tipo')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('integrantes_activos')
                    ->label('Integrantes activos')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('en_crecimiento')
                    ->label('En crecimiento')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sin_crecimiento')
                    ->label('Sin crecimiento')
                    ->state(fn (Grupo $record): int => max(((int) $record->integrantes_activos) - ((int) $record->en_crecimiento), 0))
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\Action::make('verDetalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Grupo $record): string => $this->getSummaryUrl($record)),
            ]);
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

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        if (! $user?->persona) {
            return Grupo::query()->whereRaw('1 = 0');
        }

        $growthTypeId = $this->getGrowthTypeId();

        return $user->gruposMinisterialesLiderados()
            ->with('tipoGrupo:id,nombre')
            ->addSelect([
                'integrantes_activos' => DB::table('participacion_grupos')
                    ->selectRaw('COUNT(DISTINCT persona_id)')
                    ->whereColumn('participacion_grupos.grupo_id', 'grupos.id')
                    ->where(function ($query): void {
                        $query->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                    }),
                'en_crecimiento' => DB::table('participacion_grupos as pg')
                    ->selectRaw('COUNT(DISTINCT pg.persona_id)')
                    ->whereColumn('pg.grupo_id', 'grupos.id')
                    ->where(function ($query): void {
                        $query->whereNull('pg.fecha_fin')
                            ->orWhere('pg.fecha_fin', '>=', now()->toDateString());
                    })
                    ->when($growthTypeId, function ($query, $growthTypeId): void {
                        $query->whereExists(function ($exists) use ($growthTypeId): void {
                            $exists->selectRaw('1')
                                ->from('participacion_grupos as pgc')
                                ->join('grupos as gc', 'gc.id', '=', 'pgc.grupo_id')
                                ->whereColumn('pgc.persona_id', 'pg.persona_id')
                                ->where('gc.tipo_grupo_id', $growthTypeId)
                                ->where(function ($subQuery): void {
                                    $subQuery->whereNull('pgc.fecha_fin')
                                        ->orWhere('pgc.fecha_fin', '>=', now()->toDateString());
                                });
                        });
                    }, function ($query): void {
                        $query->whereRaw('1 = 0');
                    }),
            ]);
    }
}
