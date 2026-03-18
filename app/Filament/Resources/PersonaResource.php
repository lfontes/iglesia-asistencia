<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonaResource\Pages;
use App\Filament\Resources\PersonaResource\RelationManagers\AsistenciasRelationManager;
use App\Filament\Resources\PersonaResource\RelationManagers\ParticipacionesGrupoRelationManager;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class PersonaResource extends Resource
{
    protected static ?string $model = Persona::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('apellido')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('fecha_nacimiento')
                    ->label('Fecha de nacimiento')
                    ->native(false),

                TextInput::make('telefono')
                    ->tel()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable(),

                TextColumn::make('nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable(),

                TextColumn::make('fecha_nacimiento')
                    ->date()
                    ->sortable(),

                TextColumn::make('telefono'),

                TextColumn::make('presentes_count')
                    ->label('Presentes')
                    ->sortable(),
            ])
            ->recordClasses('persona-list-row-compact')
            ->defaultSort('apellido')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'asistencias as presentes_count' => fn (Builder $query) => $query->where('presente', true),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ParticipacionesGrupoRelationManager::class,
            AsistenciasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonas::route('/'),
            'create' => Pages\CreatePersona::route('/create'),
            'view' => Pages\ViewPersona::route('/{record}'),
            'edit' => Pages\EditPersona::route('/{record}/edit'),
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
        $user = auth()->user();

        return $user?->hasRole('facilitador') && ! $user->hasRole('admin');
    }
}
