<?php

namespace App\Filament\Resources\PersonaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ParticipacionesGrupoRelationManager extends RelationManager
{
    protected static string $relationship = 'participacionesGrupo';

    protected static ?string $title = 'Participaciones por Grupo';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('grupo_id')
                    ->label('Grupo')
                    ->relationship('grupo', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('rol_grupo_id')
                    ->label('Rol')
                    ->relationship('rolGrupo', 'nombre')
                    ->placeholder('Sin rol')
                    ->searchable()
                    ->preload(),

                Forms\Components\DatePicker::make('fecha_inicio')
                    ->label('Fecha inicio')
                    ->native(false),

                Forms\Components\DatePicker::make('fecha_fin')
                    ->label('Fecha fin')
                    ->native(false),

                Forms\Components\Textarea::make('observaciones')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('grupo.nombre')
            ->columns([
                Tables\Columns\TextColumn::make('grupo.anio')
                    ->label('Anio')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('grupo.nombre')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('grupo.tipoGrupo.nombre')
                    ->label('Tipo de grupo')
                    ->placeholder('-')
                    ->searchable()
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
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
