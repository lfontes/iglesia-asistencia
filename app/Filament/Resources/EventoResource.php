<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoFechaResource\Pages\TomarAsistencia;
use App\Filament\Resources\EventoResource\Pages;
use App\Filament\Resources\EventoResource\RelationManagers\FechasRelationManager;
use App\Models\Evento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventoResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Eventos';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('tipo_evento_id')
                    ->label('Tipo de evento')
                    ->relationship('tipoEvento', 'nombre')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('activo')
                            ->default(true)
                            ->required(),
                        Forms\Components\Textarea::make('descripcion')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

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

                Tables\Columns\TextColumn::make('tipoEvento.nombre')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('fechas_count')
                    ->counts('fechas')
                    ->label('Cantidad de Fechas'),

                Tables\Columns\TextColumn::make('asistentes')
                    ->label('Asistentes')
                    ->state(function (Evento $record): string {
                        $fechas = $record->fechas;
                        $cantidadFechas = $fechas->count();

                        if ($cantidadFechas === 0) {
                            return '0';
                        }

                        $totalPresentes = (int) $fechas->sum('presentes_count');

                        if ($cantidadFechas === 1) {
                            return (string) $totalPresentes;
                        }

                        return number_format($totalPresentes / $cantidadFechas, 2);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_evento_id')
                    ->label('Tipo')
                    ->relationship('tipoEvento', 'nombre'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'fechas' => fn ($query) => $query->withCount([
                    'asistencias as presentes_count' => fn (Builder $query) => $query->where('presente', true),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FechasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventos::route('/'),
            'create' => Pages\CreateEvento::route('/create'),
            'edit' => Pages\EditEvento::route('/{record}/edit'),
            'asistencia' => TomarAsistencia::route('/{record}/asistencia'),
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

        return $user?->hasRole(['facilitador', 'lider', 'coordinador_grupos']) && ! $user->hasRole('admin');
    }
}
