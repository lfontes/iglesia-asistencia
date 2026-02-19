<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoFechaResource\Pages;
use App\Filament\Resources\EventoFechaResource\RelationManagers;
use App\Filament\Resources\EventoFechaResource\RelationManagers\AsistenciasRelationManager;
use App\Models\EventoFecha;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventoFechaResource extends Resource
{
    protected static ?string $model = EventoFecha::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('evento_id')
                    ->relationship('evento', 'nombre')
                    ->required()
                    ->searchable(),

                Forms\Components\DatePicker::make('fecha')
                    ->required(),

                Forms\Components\Textarea::make('observaciones')
                    ->columnSpanFull(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('evento.nombre')
                    ->label('Evento')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('asistencias_count')
                    ->counts('asistencias')
                    ->label('Asistentes'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }


    public static function getRelations(): array
    {
        
           return [
        AsistenciasRelationManager::class,
    ];
        
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventoFechas::route('/'),
            'create' => Pages\CreateEventoFecha::route('/create'),
            'edit' => Pages\EditEventoFecha::route('/{record}/edit'),
        ];
    }
}
