<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Ipn;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use App\Filament\Resources\IpnAulaResource\Pages\ListIpnAulas;
use App\Filament\Resources\IpnAulaResource\Pages\CreateIpnAula;
use App\Filament\Resources\IpnAulaResource\Pages\EditIpnAula;
use App\Filament\Resources\IpnAulaResource\Pages;
use App\Filament\Resources\IpnAulaResource\RelationManagers\NinosRelationManager;
use App\Filament\Resources\IpnAulaResource\RelationManagers\ServidoresRelationManager;
use App\Models\IpnAula;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpnAulaResource extends Resource
{
    protected static ?string $model = IpnAula::class;

    protected static ?string $cluster = Ipn::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Aulas';

    protected static ?string $modelLabel = 'aula IPN';

    protected static ?string $pluralModelLabel = 'aulas IPN';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('edad_desde')
                    ->label('Edad desde')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(18),

                TextInput::make('edad_hasta')
                    ->label('Edad hasta')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(18),

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

                TextColumn::make('rango_edad')
                    ->label('Rango de edad')
                    ->state(fn (IpnAula $record): string => $record->rangoEdadLabel()),

                TextColumn::make('participaciones_activas_count')
                    ->label('Niños activos')
                    ->counts('participacionesActivas')
                    ->sortable(),

                TextColumn::make('servidores_activos_count')
                    ->label('Servidores')
                    ->counts('servidoresActivos')
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
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user?->canAccessIpn()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        if ($user->canManageAllIpnAulas()) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()
            ->whereIn('id', $user->ipnAulasDisponibles()->select('id'));
    }

    public static function getRelations(): array
    {
        return [
            NinosRelationManager::class,
            ServidoresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIpnAulas::route('/'),
            'create' => CreateIpnAula::route('/create'),
            'edit' => EditIpnAula::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAccessIpn();
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->canManageIpn();
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->canManageIpn();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
