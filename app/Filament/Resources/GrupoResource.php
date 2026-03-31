<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GrupoResource\Pages;
use App\Filament\Resources\GrupoResource\RelationManagers\ParticipacionesGrupoRelationManager;
use App\Models\Grupo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Grupos';

    protected static ?string $modelLabel = 'grupo';

    protected static ?string $pluralModelLabel = 'grupos';

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('anio')
                    ->label('Anio del grupo')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue((int) date('Y') + 1)
                    ->default((int) date('Y'))
                    ->required(),

                Forms\Components\Select::make('tipo_grupo_id')
                    ->label('Tipo de grupo')
                    ->relationship('tipoGrupo', 'nombre')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('frecuencia_asistencia')
                    ->label('Frecuencia de asistencia')
                    ->options(Grupo::frecuenciasAsistencia())
                    ->live()
                    ->default(Grupo::FRECUENCIA_SEMANAL)
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

                Tables\Columns\TextColumn::make('tipoGrupo.nombre')
                    ->label('Tipo')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('anio')
                    ->label('Anio')
                    ->sortable(),

                Tables\Columns\TextColumn::make('frecuencia_asistencia')
                    ->label('Frecuencia')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Grupo::frecuenciasAsistencia()[$state] ?? ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('participantes_count')
                    ->label('Participantes')
                    ->sortable()
                    ->placeholder('0'),

                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                Tables\Filters\TernaryFilter::make('activo'),
                Tables\Filters\SelectFilter::make('tipo_grupo_id')
                    ->label('Tipo')
                    ->relationship('tipoGrupo', 'nombre'),
                Tables\Filters\SelectFilter::make('frecuencia_asistencia')
                    ->label('Frecuencia')
                    ->options(Grupo::frecuenciasAsistencia()),
            ])
            ->actions([
                Action::make('participacion')
                    ->label('Registrar Participacion')
                    ->url(fn ($record) => GrupoResource::getUrl('participacion', [
                        'record' => $record,
                    ]))
                    ->icon('heroicon-o-user-plus'),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListGrupos::route('/'),
            'create' => Pages\CreateGrupo::route('/create'),
            'edit' => Pages\EditGrupo::route('/{record}/edit'),
            'participacion' => Pages\RegistrarParticipacion::route('/{record}/participacion'),
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
        return ! static::isSoloFacilitador() && parent::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! static::isSoloFacilitador() && parent::shouldRegisterNavigation();
    }

    protected static function isSoloFacilitador(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('facilitador') && ! $user->hasRole('admin');
    }
}
