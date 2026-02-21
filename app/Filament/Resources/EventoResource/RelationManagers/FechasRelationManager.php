<?php

namespace App\Filament\Resources\EventoResource\RelationManagers;

use App\Filament\Resources\EventoFechaResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            ->recordUrl(fn ($record): string => EventoFechaResource::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('asistencias_count')
                    ->label('Asistentes')
                    ->counts([
                        'asistencias' => fn (Builder $query) => $query->where('presente', true),
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record): string => EventoFechaResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
