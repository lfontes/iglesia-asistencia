<?php

namespace App\Filament\Resources\PersonaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IpnAulasServidorRelationManager extends RelationManager
{
    protected static string $relationship = 'ipnAulasServidor';

    protected static ?string $title = 'IPN: maestro / servidor';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('ipn_aula_id')
                ->label('Aula')
                ->relationship('aula', 'nombre')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('rol')
                ->label('Rol en el aula')
                ->placeholder('Maestro, ayudante, servidor')
                ->maxLength(100),
            Forms\Components\DatePicker::make('fecha_inicio')
                ->label('Fecha inicio')
                ->native(false),
            Forms\Components\DatePicker::make('fecha_fin')
                ->label('Fecha fin')
                ->native(false),
            Forms\Components\Toggle::make('activo')
                ->default(true)
                ->required(),
            Forms\Components\Textarea::make('observaciones')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('aula.nombre')
            ->columns([
                Tables\Columns\TextColumn::make('aula.nombre')
                    ->label('Aula IPN')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rol')
                    ->label('Rol')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageIpn()),
            ]);
    }
}
