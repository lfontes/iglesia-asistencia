<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Persona;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static string | \UnitEnum | null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 200;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email de acceso')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Select::make('persona_id')
                    ->label('Persona vinculada')
                    ->relationship('persona', 'apellido')
                    ->searchable(['nombre', 'apellido', 'telefono', 'email'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Persona $record): string => trim($record->apellido.' '.$record->nombre).($record->email ? " ({$record->email})" : ''))
                    ->placeholder('Sin vincular'),

                Select::make('role_names')
                    ->label('Roles de acceso')
                    ->options(fn (): array => Role::query()
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->all())
                    ->multiple()
                    ->minItems(1)
                    ->required()
                    ->dehydrated(false),

                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('persona.apellido')
                    ->label('Persona vinculada')
                    ->formatStateUsing(fn ($state, User $record): string => $record->persona ? trim($record->persona->apellido.' '.$record->persona->nombre) : '-')
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->sortable(false),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
