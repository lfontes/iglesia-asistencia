<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MetagrupoResource\Pages;
use App\Models\Metagrupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Filament\Actions\Action as ModalAction;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MetagrupoResource extends Resource
{
    protected static ?string $model = Metagrupo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Metagrupos';

    protected static ?string $modelLabel = 'metagrupo';

    protected static ?string $pluralModelLabel = 'metagrupos';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('lider_persona_id')
                    ->label('Líder')
                    ->relationship('lider', 'apellido')
                    ->searchable(['nombre', 'apellido', 'telefono'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Persona $record): string => trim($record->apellido.' '.$record->nombre))
                    ->placeholder('Sin líder asignado'),

                Hidden::make('tipo_grupo_filtro')
                    ->default(null),
                Hidden::make('activo_filtro')
                    ->default(null),
                Hidden::make('anio_filtro')
                    ->default(null),

                Actions::make([
                    Action::make('abrirFiltros')
                        ->label('Filtros')
                        ->icon('heroicon-m-funnel')
                        ->modalHeading('Filtros')
                        ->modalSubmitActionLabel('Aplicar')
                        ->modalCancelActionLabel('Cerrar')
                        ->form([
                            Forms\Components\Select::make('tipo_grupo_filtro')
                                ->label('Tipo de grupo')
                                ->options(fn (): array => self::tiposGrupoOptions())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->placeholder('Todos'),
                            Forms\Components\Select::make('activo_filtro')
                                ->label('Activo')
                                ->options([
                                    '1' => 'Activos',
                                    '0' => 'Inactivos',
                                ])
                                ->native(false)
                                ->placeholder('Todos'),
                            Forms\Components\Select::make('anio_filtro')
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

                Forms\Components\CheckboxList::make('grupos')
                    ->relationship(
                        'grupos',
                        'nombre',
                        fn (Builder $query, Get $get): Builder => $query
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

                Forms\Components\Toggle::make('activo')
                    ->default(true)
                    ->required(),

                Forms\Components\Textarea::make('descripcion')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lider.apellido')
                    ->label('Líder')
                    ->formatStateUsing(fn ($state, Metagrupo $record): string => $record->lider ? trim($record->lider->apellido.' '.$record->lider->nombre) : '-')
                    ->searchable(['personas.apellido', 'personas.nombre'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('grupos_count')
                    ->label('Grupos')
                    ->sortable(),

                Tables\Columns\TextColumn::make('personas_count')
                    ->label('Personas')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['lider:id,nombre,apellido'])
            ->withSummaryColumns();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetagrupos::route('/'),
            'create' => Pages\CreateMetagrupo::route('/create'),
            'view' => Pages\ViewMetagrupo::route('/{record}'),
            'edit' => Pages\EditMetagrupo::route('/{record}/edit'),
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
        return (bool) auth()->user()?->hasRole('admin');
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
        return \App\Models\Grupo::query()
            ->whereNotNull('anio')
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio', 'anio')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
            ->all();
    }
}
