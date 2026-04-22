<?php

namespace App\Filament\Resources\EventoFechaResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InscripcionesRelationManager extends RelationManager
{
    protected static string $relationship = 'inscripciones';

    protected static ?string $title = 'Inscriptos';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('persona_id')
            ->columns([
                Tables\Columns\TextColumn::make('persona.apellido')
                    ->label('Apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),

                Tables\Columns\TextColumn::make('persona.nombre')
                    ->label('Nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),

                Tables\Columns\TextColumn::make('persona.telefono')
                    ->label('Teléfono')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('persona.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'inscripto' => 'success',
                        'cancelado' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Inscripto el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
