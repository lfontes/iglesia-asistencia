<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RolGrupoResource\Pages\CreateRolGrupo;
use App\Filament\Resources\RolGrupoResource\Pages\EditRolGrupo;
use App\Filament\Resources\RolGrupoResource\Pages\ListRolGrupos;
use App\Models\RolGrupo;
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

class RolGrupoResource extends Resource
{
    protected static ?string $model = RolGrupo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Roles de grupo';

    protected static ?string $modelLabel = 'rol de grupo';

    protected static ?string $pluralModelLabel = 'roles de grupo';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 11;

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
            'index' => ListRolGrupos::route('/'),
            'create' => CreateRolGrupo::route('/create'),
            'edit' => EditRolGrupo::route('/{record}/edit'),
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
