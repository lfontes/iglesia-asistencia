<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MetagrupoResource\Pages\CreateMetagrupo;
use App\Filament\Resources\MetagrupoResource\Pages\EditMetagrupo;
use App\Filament\Resources\MetagrupoResource\Pages\ListMetagrupos;
use App\Filament\Resources\MetagrupoResource\Pages\ViewMetagrupo;
use App\Models\Grupo;
use App\Models\Metagrupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Filament\Actions\Action as ModalAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MetagrupoResource extends Resource
{
    protected static ?string $model = Metagrupo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Metagrupos';

    protected static ?string $modelLabel = 'metagrupo';

    protected static ?string $pluralModelLabel = 'metagrupos';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Select::make('lider_persona_id')
                    ->label('Líder')
                    ->relationship('lider', 'apellido')
                    ->searchable(['nombre', 'apellido', 'telefono'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Persona $record): string => trim($record->apellido.' '.$record->nombre))
                    ->placeholder('Sin líder asignado')
                    ->disabled(fn (): bool => ! auth()->user()?->hasRole('admin'))
                    ->dehydrated(fn (): bool => (bool) auth()->user()?->hasRole('admin')),

                Hidden::make('tipo_grupo_filtro')
                    ->default(null),
                Hidden::make('activo_filtro')
                    ->default(null),
                Hidden::make('anio_filtro')
                    ->default(null),

                Actions::make([
                    ModalAction::make('abrirFiltros')
                        ->label('Filtros')
                        ->icon('heroicon-m-funnel')
                        ->modalHeading('Filtros')
                        ->modalSubmitActionLabel('Aplicar')
                        ->modalCancelActionLabel('Cerrar')
                        ->schema([
                            Select::make('tipo_grupo_filtro')
                                ->label('Tipo de grupo')
                                ->options(fn (): array => self::tiposGrupoOptions())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->placeholder('Todos'),
                            Select::make('activo_filtro')
                                ->label('Activo')
                                ->options([
                                    '1' => 'Activos',
                                    '0' => 'Inactivos',
                                ])
                                ->native(false)
                                ->placeholder('Todos'),
                            Select::make('anio_filtro')
                                ->label('Año')
                                ->options(fn (): array => self::aniosGrupoOptions())
                                ->native(false)
                                ->placeholder('Todos'),
                        ])
                        ->extraModalFooterActions([
                            ModalAction::make('resetearFiltros')
                                ->label('Resetear filtros')
                                ->color('gray')
                                ->close()
                                ->action(function (Set $set): void {
                                    $set('tipo_grupo_filtro', null);
                                    $set('activo_filtro', null);
                                    $set('anio_filtro', null);
                                }),
                        ])
                        ->action(function (array $data, Set $set): void {
                            $set('tipo_grupo_filtro', $data['tipo_grupo_filtro'] ?? null);
                            $set('activo_filtro', $data['activo_filtro'] ?? null);
                            $set('anio_filtro', $data['anio_filtro'] ?? null);
                        }),
                ])
                    ->alignStart()
                    ->columnSpanFull(),

                Placeholder::make('filtro_resumen')
                    ->label('Filtro activo')
                    ->content(function (Get $get): string {
                        $options = self::tiposGrupoOptions();
                        $tipoId = $get('tipo_grupo_filtro');
                        $activo = $get('activo_filtro');
                        $anio = $get('anio_filtro');

                        $tipoLabel = $tipoId && isset($options[$tipoId])
                            ? $options[$tipoId]
                            : 'Todos';

                        $activoLabel = match ($activo) {
                            '1' => 'Activos',
                            '0' => 'Inactivos',
                            default => 'Todos',
                        };

                        $anioLabel = $anio ?: 'Todos';

                        return "Tipo: {$tipoLabel} · Activo: {$activoLabel} · Año: {$anioLabel}";
                    })
                    ->columnSpanFull(),

                CheckboxList::make('grupos')
                    ->relationship(
                        'grupos',
                        'nombre',
                        fn (Builder $query, Get $get): Builder => $query
                            ->when(
                                ! auth()->user()?->hasRole('admin'),
                                fn (Builder $subQuery): Builder => $subQuery->managedBy(auth()->user())
                            )
                            ->when(
                                $get('tipo_grupo_filtro'),
                                fn (Builder $subQuery, $tipoId): Builder => $subQuery->where('tipo_grupo_id', $tipoId)
                            )
                            ->when(
                                $get('activo_filtro') !== null && $get('activo_filtro') !== '',
                                fn (Builder $subQuery) => $subQuery->where('activo', (bool) ((int) $get('activo_filtro')))
                            )
                            ->when(
                                $get('anio_filtro'),
                                fn (Builder $subQuery, $anio) => $subQuery->where('anio', (int) $anio)
                            )
                            ->orderBy('nombre')
                    )
                    ->label('Grupos incluidos')
                    ->searchable()
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),

                Toggle::make('activo')
                    ->default(true)
                    ->required(),

                Textarea::make('descripcion')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lider.apellido')
                    ->label('Líder')
                    ->formatStateUsing(fn ($state, Metagrupo $record): string => $record->lider ? trim($record->lider->apellido.' '.$record->lider->nombre) : '-')
                    ->searchable(['personas.apellido', 'personas.nombre'])
                    ->sortable(),

                TextColumn::make('grupos_count')
                    ->label('Grupos')
                    ->sortable(),

                TextColumn::make('personas_count')
                    ->label('Personas')
                    ->sortable(),

                IconColumn::make('activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['lider:id,nombre,apellido'])
            ->withSummaryColumns();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetagrupos::route('/'),
            'create' => CreateMetagrupo::route('/create'),
            'view' => ViewMetagrupo::route('/{record}'),
            'edit' => EditMetagrupo::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole(['admin', 'lider']);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('lider')
            && $user->persona
            && (int) $record->lider_persona_id === (int) $user->persona->id;
    }

    public static function canEdit($record): bool
    {
        return static::canView($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    /**
     * @return array<string, string>
     */
    protected static function tiposGrupoOptions(): array
    {
        return TipoGrupo::query()
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function aniosGrupoOptions(): array
    {
        return Grupo::query()
            ->whereNotNull('anio')
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio', 'anio')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
            ->all();
    }
}
