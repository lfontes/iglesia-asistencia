<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\GrupoResource\Pages\ListGrupos;
use App\Filament\Resources\GrupoResource\Pages\CreateGrupo;
use App\Filament\Resources\GrupoResource\Pages\EditGrupo;
use App\Filament\Resources\GrupoResource\Pages\RegistrarParticipacion;
use App\Filament\Resources\GrupoResource\Pages;
use App\Filament\Resources\GrupoResource\RelationManagers\ParticipacionesGrupoRelationManager;
use App\Models\Grupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Grupos';

    protected static ?string $modelLabel = 'grupo';

    protected static ?string $pluralModelLabel = 'grupos';

    protected static string | \UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('anio')
                    ->label('Anio del grupo')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue((int) date('Y') + 1)
                    ->default((int) date('Y'))
                    ->required(),

                Select::make('tipo_grupo_id')
                    ->label('Tipo de grupo')
                    ->relationship('tipoGrupo', 'nombre')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if (static::isTipoMinisterio($state)) {
                            $set('frecuencia_asistencia', Grupo::FRECUENCIA_SIN_ASISTENCIA);
                        }
                    }),

                Select::make('frecuencia_asistencia')
                    ->label('Frecuencia de asistencia')
                    ->options(Grupo::frecuenciasAsistencia())
                    ->live()
                    ->default(Grupo::FRECUENCIA_SEMANAL)
                    ->disabled(fn (Get $get): bool => static::isTipoMinisterio($get('tipo_grupo_id')))
                    ->dehydrated()
                    ->required(),

                Select::make('lider_persona_id')
                    ->label('Líder del grupo')
                    ->relationship('lider', 'apellido')
                    ->getOptionLabelFromRecordUsing(fn (Persona $record): string => trim($record->apellido.' '.$record->nombre))
                    ->searchable(['nombre', 'apellido', 'telefono'])
                    ->preload()
                    ->required()
                    ->placeholder('Selecciona un líder')
                    ->default(fn (): ?int => auth()->user()?->persona_id)
                    ->dehydrated(),

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

                TextColumn::make('tipoGrupo.nombre')
                    ->label('Tipo')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lider.apellido')
                    ->label('Líder')
                    ->formatStateUsing(fn ($state, Grupo $record): string => $record->lider ? trim($record->lider->apellido.' '.$record->lider->nombre) : '-')
                    ->searchable(['personas.apellido', 'personas.nombre'])
                    ->sortable(),

                TextColumn::make('anio')
                    ->label('Anio')
                    ->sortable(),

                TextColumn::make('frecuencia_asistencia')
                    ->label('Frecuencia')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Grupo::frecuenciasAsistencia()[$state] ?? ucfirst($state))
                    ->sortable(),

                TextColumn::make('participantes_count')
                    ->label('Participantes')
                    ->sortable()
                    ->placeholder('0'),

                IconColumn::make('activo')
                    ->boolean(),

                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo'),
                SelectFilter::make('anio')
                    ->label('Anio')
                    ->options(fn (): array => Grupo::query()
                        ->whereNotNull('anio')
                        ->distinct()
                        ->orderByDesc('anio')
                        ->pluck('anio', 'anio')
                        ->mapWithKeys(fn ($anio): array => [(string) $anio => (string) $anio])
                        ->all()),
                SelectFilter::make('tipo_grupo_id')
                    ->label('Tipo')
                    ->relationship('tipoGrupo', 'nombre')
                    ->default(fn (): ?int => auth()->user()?->hasRole('coordinador_grupos')
                        ? static::tipoCrecimientoId()
                        : null),
                SelectFilter::make('frecuencia_asistencia')
                    ->label('Frecuencia')
                    ->options(Grupo::frecuenciasAsistencia()),
            ])
            ->recordActions([
                Action::make('participacion')
                    ->label('Registrar participantes')
                    ->url(fn ($record) => GrupoResource::getUrl('participacion', [
                        'record' => $record,
                    ]))
                    ->icon('heroicon-o-user-plus'),

                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'participantes_count' => DB::table('participacion_grupos')
                ->selectRaw('COUNT(DISTINCT persona_id)')
                ->whereColumn('participacion_grupos.grupo_id', 'grupos.id'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrupos::route('/'),
            'create' => CreateGrupo::route('/create'),
            'edit' => EditGrupo::route('/{record}/edit'),
            'participacion' => RegistrarParticipacion::route('/{record}/participacion'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ParticipacionesGrupoRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageGrupos() ?? false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->canManageGrupos() || $user?->canCreateGrupos());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canCreateGrupos() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->canManageGrupo($record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->canManageGrupo($record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->canManageGrupos() ?? false;
    }

    protected static function isTipoMinisterio($tipoGrupoId): bool
    {
        if (! filled($tipoGrupoId)) {
            return false;
        }

        return TipoGrupo::query()
            ->whereKey($tipoGrupoId)
            ->whereRaw('LOWER(nombre) = ?', ['ministerio'])
            ->exists();
    }

    protected static function tipoCrecimientoId(): ?int
    {
        $exactMatch = TipoGrupo::query()
            ->whereRaw('LOWER(nombre) = ?', ['crecimiento'])
            ->value('id');

        if ($exactMatch) {
            return (int) $exactMatch;
        }

        $partialMatch = TipoGrupo::query()
            ->whereRaw('LOWER(nombre) LIKE ?', ['%crecimiento%'])
            ->orderBy('nombre')
            ->value('id');

        return $partialMatch ? (int) $partialMatch : null;
    }
}
