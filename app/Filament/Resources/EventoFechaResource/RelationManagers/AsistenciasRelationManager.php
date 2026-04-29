<?php

namespace App\Filament\Resources\EventoFechaResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Models\Persona;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AsistenciasRelationManager extends RelationManager
{
    protected static string $relationship = 'asistencias';

    protected static ?string $title = 'Asistencias';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('persona_id')
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

            Toggle::make('presente')
                ->label('Presente')
                ->default(true),

            Textarea::make('observaciones')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('persona.apellido')
                    ->label('Apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),

                TextColumn::make('persona.nombre')
                    ->label('Nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),

                IconColumn::make('presente')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->size('sm'),

                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
