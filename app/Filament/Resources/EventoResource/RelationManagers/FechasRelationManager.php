<?php

namespace App\Filament\Resources\EventoResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class FechasRelationManager extends RelationManager
{
    protected static string $relationship = 'fechas';

    protected static ?string $title = 'Fechas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('fecha')
                ->required(),

            Forms\Components\Textarea::make('observaciones')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->date()
                    ->sortable(),

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
