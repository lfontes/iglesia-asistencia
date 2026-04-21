<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpnNinoResource\Pages;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpnNinoResource extends Resource
{
    protected static ?string $model = Persona::class;

    protected static ?string $navigationIcon = 'heroicon-o-face-smile';

    protected static ?string $navigationLabel = 'Niños';

    protected static ?string $modelLabel = 'niño IPN';

    protected static ?string $pluralModelLabel = 'niños IPN';

    protected static ?string $navigationGroup = 'IPN';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('id')
                    ->label('Persona ID')
                    ->content(fn (?Persona $record): string => $record ? (string) $record->id : 'Se asigna al guardar')
                    ->visible(fn (?Persona $record): bool => $record !== null),

                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('apellido')
                    ->required()
                    ->maxLength(255),

                Forms\Components\DatePicker::make('fecha_nacimiento')
                    ->label('Fecha de nacimiento')
                    ->native(false),

                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono del niño')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                Forms\Components\Select::make('departamento')
                    ->label('Departamento')
                    ->options(Persona::departamentosMendoza())
                    ->searchable()
                    ->placeholder('Selecciona un departamento'),

                Forms\Components\Select::make('responsable_persona_id')
                    ->label('Responsable / tutor')
                    ->searchable()
                    ->preload(false)
                    ->getSearchResultsUsing(fn (string $search): array => Persona::query()
                        ->buscarPorNombreApellido($search)
                        ->orderBy('apellido')
                        ->orderBy('nombre')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Persona $persona): array => [
                            $persona->id => static::personaOptionLabel($persona),
                        ])
                        ->all())
                    ->getOptionLabelUsing(function ($value): ?string {
                        $persona = $value ? Persona::query()->find($value) : null;

                        return $persona ? static::personaOptionLabel($persona) : null;
                    })
                    ->helperText('Selecciona una persona existente de la base de datos.'),

                Forms\Components\Textarea::make('observaciones_ipn')
                    ->label('Observaciones importantes')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Persona ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->buscarPorNombreApellido($search))
                    ->sortable(),

                Tables\Columns\TextColumn::make('edad')
                    ->label('Edad')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} años" : '-')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('responsablePersona.apellido')
                    ->label('Responsable')
                    ->formatStateUsing(fn ($state, Persona $record): string => $record->responsableIpnLabel() ?: '-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'responsablePersona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('responsablePersona.telefono')
                    ->label('Tel. responsable')
                    ->formatStateUsing(fn ($state, Persona $record): string => $record->responsableIpnTelefono() ?: '-')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('ipnAulas.nombre')
                    ->label('Aulas')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('-'),
            ])
            ->defaultSort('apellido')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('es_menor', true)
            ->with(['ipnAulas:id,nombre', 'responsablePersona:id,nombre,apellido,telefono']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIpnNinos::route('/'),
            'create' => Pages\CreateIpnNino::route('/create'),
            'edit' => Pages\EditIpnNino::route('/{record}/edit'),
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

    protected static function personaOptionLabel(Persona $persona): string
    {
        return trim("{$persona->id} - {$persona->apellido} {$persona->nombre}") . ($persona->telefono ? " ({$persona->telefono})" : '');
    }
}
