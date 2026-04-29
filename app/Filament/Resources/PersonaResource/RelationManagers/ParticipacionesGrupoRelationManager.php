<?php

namespace App\Filament\Resources\PersonaResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
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

class ParticipacionesGrupoRelationManager extends RelationManager
{
    protected static string $relationship = 'participacionesGrupo';

    protected static ?string $title = 'Grupos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('grupo_id')
                    ->label('Grupo')
                    ->relationship('grupo', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('rol_grupo_id')
                    ->label('Rol')
                    ->relationship('rolGrupo', 'nombre')
                    ->placeholder('Sin rol')
                    ->searchable()
                    ->preload(),

                Toggle::make('recibe_recordatorios')
                    ->label('Recibe recordatorios')
                    ->helperText('Si esta persona facilita este grupo, esta marca la prioriza para recibir recordatorios.'),

                DatePicker::make('fecha_inicio')
                    ->label('Fecha inicio')
                    ->native(false),

                DatePicker::make('fecha_fin')
                    ->label('Fecha fin')
                    ->native(false),

                Textarea::make('observaciones')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('grupo.nombre')
            ->columns([
                TextColumn::make('grupo.anio')
                    ->label('Anio')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('grupo.nombre')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grupo.tipoGrupo.nombre')
                    ->label('Tipo de grupo')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rolGrupo.nombre')
                    ->label('Rol')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('recibe_recordatorios')
                    ->label('Recordatorios')
                    ->boolean(),

                TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),

                TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
