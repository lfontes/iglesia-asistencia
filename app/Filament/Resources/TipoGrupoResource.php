<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoGrupoResource\Pages\CreateTipoGrupo;
use App\Filament\Resources\TipoGrupoResource\Pages\EditTipoGrupo;
use App\Filament\Resources\TipoGrupoResource\Pages\ListTipoGrupos;
use App\Models\TipoGrupo;
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

class TipoGrupoResource extends Resource
{
    protected static ?string $model = TipoGrupo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Tipos de grupo';

    protected static ?string $modelLabel = 'tipo de grupo';

    protected static ?string $pluralModelLabel = 'tipos de grupo';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 9;

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
            'index' => ListTipoGrupos::route('/'),
            'create' => CreateTipoGrupo::route('/create'),
            'edit' => EditTipoGrupo::route('/{record}/edit'),
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
