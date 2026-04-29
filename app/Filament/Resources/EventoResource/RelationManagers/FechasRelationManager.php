<?php

namespace App\Filament\Resources\EventoResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\EventoFechaResource;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FechasRelationManager extends RelationManager
{
    protected static string $relationship = 'fechas';

    protected static ?string $title = 'Fechas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fecha')
                ->required(),

            Textarea::make('observaciones')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => EventoFechaResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('fecha')
                    ->date()
                    ->sortable(),

                TextColumn::make('asistencias_count')
                    ->label('Asistentes')
                    ->counts([
                        'asistencias' => fn (Builder $query) => $query->where('presente', true),
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record): string => EventoFechaResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
