<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpnAulaResource\Pages;
use App\Filament\Resources\IpnAulaResource\RelationManagers\NinosRelationManager;
use App\Filament\Resources\IpnAulaResource\RelationManagers\ServidoresRelationManager;
use App\Models\IpnAula;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpnAulaResource extends Resource
{
    protected static ?string $model = IpnAula::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Aulas';

    protected static ?string $modelLabel = 'aula IPN';

    protected static ?string $pluralModelLabel = 'aulas IPN';

    protected static ?string $navigationGroup = 'IPN';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('edad_desde')
                    ->label('Edad desde')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(18),

                Forms\Components\TextInput::make('edad_hasta')
                    ->label('Edad hasta')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(18),

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

                Tables\Columns\TextColumn::make('rango_edad')
                    ->label('Rango de edad')
                    ->state(fn (IpnAula $record): string => $record->rangoEdadLabel()),

                Tables\Columns\TextColumn::make('participaciones_activas_count')
                    ->label('Niños activos')
                    ->counts('participacionesActivas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('servidores_activos_count')
                    ->label('Servidores')
                    ->counts('servidoresActivos')
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
            ->bulkActions([]);
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
            'index' => Pages\ListIpnAulas::route('/'),
            'create' => Pages\CreateIpnAula::route('/create'),
            'edit' => Pages\EditIpnAula::route('/{record}/edit'),
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
