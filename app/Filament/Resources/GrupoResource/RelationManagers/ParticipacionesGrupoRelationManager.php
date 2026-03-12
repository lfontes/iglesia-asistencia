<?php

namespace App\Filament\Resources\GrupoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipacionesGrupoRelationManager extends RelationManager
{
    protected static string $relationship = 'participacionesGrupo';

    protected static ?string $title = 'Personas Participantes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('rol_grupo_id')
                ->label('Rol')
                ->relationship('rolGrupo', 'nombre')
                ->placeholder('Sin rol')
                ->searchable()
                ->preload(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('persona.apellido')
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

                Tables\Columns\TextColumn::make('rolGrupo.nombre')
                    ->label('Rol')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rol_grupo_id')
                    ->label('Rol')
                    ->relationship('rolGrupo', 'nombre'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
