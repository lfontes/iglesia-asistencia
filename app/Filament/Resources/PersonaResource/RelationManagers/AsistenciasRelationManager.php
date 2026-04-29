<?php

namespace App\Filament\Resources\PersonaResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AsistenciasRelationManager extends RelationManager
{
    protected static string $relationship = 'asistencias';

    protected static ?string $title = 'Eventos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('presente', true))
            ->columns([
                TextColumn::make('eventoFecha.evento.nombre')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('eventoFecha.fecha')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
