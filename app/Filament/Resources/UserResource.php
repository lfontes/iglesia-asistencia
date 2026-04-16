<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Persona;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email de acceso')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('persona_id')
                    ->label('Persona vinculada')
                    ->relationship('persona', 'apellido')
                    ->searchable(['nombre', 'apellido', 'telefono', 'email'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Persona $record): string => trim($record->apellido.' '.$record->nombre).($record->email ? " ({$record->email})" : ''))
                    ->placeholder('Sin vincular'),

                Forms\Components\Select::make('role')
                    ->label('Rol de acceso')
                    ->options(fn (): array => Role::query()
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->all())
                    ->required()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('password')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('persona.apellido')
                    ->label('Persona vinculada')
                    ->formatStateUsing(fn ($state, User $record): string => $record->persona ? trim($record->persona->apellido.' '.$record->persona->nombre) : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->sortable(false),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
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
