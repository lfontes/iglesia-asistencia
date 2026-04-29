<?php

namespace App\Filament\Resources\PersonaResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IpnAulasServidorRelationManager extends RelationManager
{
    protected static string $relationship = 'ipnAulasServidor';

    protected static ?string $title = 'IPN: maestro / servidor';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ipn_aula_id')
                ->label('Aula')
                ->relationship('aula', 'nombre')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('rol')
                ->label('Rol en el aula')
                ->placeholder('Maestro, ayudante, servidor')
                ->maxLength(100),
            DatePicker::make('fecha_inicio')
                ->label('Fecha inicio')
                ->native(false),
            DatePicker::make('fecha_fin')
                ->label('Fecha fin')
                ->native(false),
            Toggle::make('activo')
                ->default(true)
                ->required(),
            Textarea::make('observaciones')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('aula.nombre')
            ->columns([
                TextColumn::make('aula.nombre')
                    ->label('Aula IPN')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->placeholder('-')
                    ->searchable(),
                IconColumn::make('activo')
                    ->boolean(),
                TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),
                TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),
                TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
                DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
            ]);
    }
}
