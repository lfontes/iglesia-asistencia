<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\EventoResource\Pages\ListEventos;
use App\Filament\Resources\EventoResource\Pages\CreateEvento;
use App\Filament\Resources\EventoResource\Pages\EditEvento;
use App\Filament\Resources\EventoFechaResource\Pages\TomarAsistencia;
use App\Filament\Resources\EventoResource\Pages;
use App\Filament\Resources\EventoResource\RelationManagers\FechasRelationManager;
use App\Models\Evento;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventoResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Eventos';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Select::make('tipo_evento_id')
                    ->label('Tipo de evento')
                    ->relationship('tipoEvento', 'nombre')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('activo')
                            ->default(true)
                            ->required(),
                        Textarea::make('descripcion')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

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

                TextColumn::make('tipoEvento.nombre')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('fechas_count')
                    ->counts('fechas')
                    ->label('Cantidad de Fechas'),

                TextColumn::make('asistentes')
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

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo_evento_id')
                    ->label('Tipo')
                    ->relationship('tipoEvento', 'nombre'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListEventos::route('/'),
            'create' => CreateEvento::route('/create'),
            'edit' => EditEvento::route('/{record}/edit'),
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
