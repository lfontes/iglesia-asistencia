<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use App\Filament\Resources\PersonaResource\Pages\ListPersonas;
use App\Filament\Resources\PersonaResource\Pages\CreatePersona;
use App\Filament\Resources\PersonaResource\Pages\ViewPersona;
use App\Filament\Resources\PersonaResource\Pages\EditPersona;
use App\Filament\Resources\PersonaResource\Pages;
use App\Filament\Resources\PersonaResource\RelationManagers\AsistenciasRelationManager;
use App\Filament\Resources\PersonaResource\RelationManagers\IpnAulasServidorRelationManager;
use App\Filament\Resources\PersonaResource\RelationManagers\ParticipacionesGrupoRelationManager;
use App\Models\Persona;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonaResource extends Resource
{
    protected static ?string $model = Persona::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Personas';

    protected static ?string $modelLabel = 'persona';

    protected static ?string $pluralModelLabel = 'personas';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                Select::make('departamento')
                    ->label('Departamento')
                    ->options(Persona::departamentosMendoza())
                    ->searchable()
                    ->placeholder('Selecciona un departamento'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('fecha_nacimiento')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('edad')
                    ->label('Edad')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? (string) $state : '')
                    ->toggleable(),

                TextColumn::make('telefono')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('departamento')
                    ->label('Departamento')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('presentes_count')
                    ->label('Presentes')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordClasses('persona-list-row-compact')
            ->defaultSort('apellido')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
            IpnAulasServidorRelationManager::class,
            AsistenciasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonas::route('/'),
            'create' => CreatePersona::route('/create'),
            'view' => ViewPersona::route('/{record}'),
            'edit' => EditPersona::route('/{record}/edit'),
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

        return $user?->hasRole(['facilitador', 'lider', 'coordinador_grupos']) && ! $user->hasRole('admin');
    }
}
