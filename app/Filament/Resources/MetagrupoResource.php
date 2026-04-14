<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MetagrupoResource\Pages;
use App\Models\Metagrupo;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MetagrupoResource extends Resource
{
    protected static ?string $model = Metagrupo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Metagrupos';

    protected static ?string $modelLabel = 'metagrupo';

    protected static ?string $pluralModelLabel = 'metagrupos';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('lider_persona_id')
                    ->label('Líder')
                    ->relationship('lider', 'apellido')
                    ->searchable(['nombre', 'apellido', 'telefono'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Persona $record): string => trim($record->apellido.' '.$record->nombre))
                    ->placeholder('Sin líder asignado'),

                Forms\Components\CheckboxList::make('grupos')
                    ->relationship('grupos', 'nombre')
                    ->label('Grupos incluidos')
                    ->searchable()
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),

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

                Tables\Columns\TextColumn::make('lider.apellido')
                    ->label('Líder')
                    ->formatStateUsing(fn ($state, Metagrupo $record): string => $record->lider ? trim($record->lider->apellido.' '.$record->lider->nombre) : '-')
                    ->searchable(['personas.apellido', 'personas.nombre'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('grupos_count')
                    ->label('Grupos')
                    ->sortable(),

                Tables\Columns\TextColumn::make('personas_count')
                    ->label('Personas')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['lider:id,nombre,apellido'])
            ->withSummaryColumns();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetagrupos::route('/'),
            'create' => Pages\CreateMetagrupo::route('/create'),
            'view' => Pages\ViewMetagrupo::route('/{record}'),
            'edit' => Pages\EditMetagrupo::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole(['admin', 'lider']);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('lider')
            && $user->persona
            && (int) $record->lider_persona_id === (int) $user->persona->id;
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }
}
