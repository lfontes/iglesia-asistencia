<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoEventoResource\Pages\CreateTipoEvento;
use App\Filament\Resources\TipoEventoResource\Pages\EditTipoEvento;
use App\Filament\Resources\TipoEventoResource\Pages\ListTipoEventos;
use App\Models\TipoEvento;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TipoEventoResource extends Resource
{
    protected static ?string $model = TipoEvento::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Tipos de evento';

    protected static ?string $modelLabel = 'tipo de evento';

    protected static ?string $pluralModelLabel = 'tipos de evento';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Toggle::make('activo')
                    ->default(true)
                    ->required(),

                Textarea::make('descripcion')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTipoEventos::route('/'),
            'create' => CreateTipoEvento::route('/create'),
            'edit' => EditTipoEvento::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return ! static::isSoloFacilitador() && parent::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! static::isSoloFacilitador() && parent::shouldRegisterNavigation();
    }

    protected static function isSoloFacilitador(): bool
    {
        return (bool) auth()->user()?->isRestrictedPanelUser();
    }
}
