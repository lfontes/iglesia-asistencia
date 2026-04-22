<?php

namespace App\Filament\Resources\EventoFechaResource\RelationManagers;

use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AsistenciasRelationManager extends RelationManager
{
    protected static string $relationship = 'asistencias';

    protected static ?string $title = 'Asistencias';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('persona_id')
                ->label('Persona')
                ->options(
                    Persona::query()
                        ->orderBy('apellido')
                        ->orderBy('nombre')
                        ->get()
                        ->mapWithKeys(fn (Persona $persona) => [
                            $persona->id => trim("{$persona->apellido} {$persona->nombre}"),
                        ])
                        ->all()
                )
                ->searchable()
                ->required(),

            Forms\Components\Toggle::make('presente')
                ->label('Presente')
                ->default(true),

            Forms\Components\Textarea::make('observaciones')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
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

                Tables\Columns\IconColumn::make('presente')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
