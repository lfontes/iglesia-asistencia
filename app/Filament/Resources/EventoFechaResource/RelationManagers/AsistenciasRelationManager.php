<?php

namespace App\Filament\Resources\EventoFechaResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use App\Models\Persona;

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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('persona.nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('presente')
                    ->boolean(),

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
