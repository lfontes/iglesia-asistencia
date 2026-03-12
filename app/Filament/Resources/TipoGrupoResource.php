<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoGrupoResource\Pages;
use App\Models\TipoGrupo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoGrupoResource extends Resource
{
    protected static ?string $model = TipoGrupo::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalogos';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Toggle::make('activo')
                    ->default(true)
                    ->required(),

                Forms\Components\Textarea::make('descripcion')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoGrupos::route('/'),
            'create' => Pages\CreateTipoGrupo::route('/create'),
            'edit' => Pages\EditTipoGrupo::route('/{record}/edit'),
        ];
    }
}

