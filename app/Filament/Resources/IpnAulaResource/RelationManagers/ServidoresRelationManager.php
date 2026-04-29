<?php

namespace App\Filament\Resources\IpnAulaResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Actions\EditAction;
use App\Models\IpnAulaServidor;
use App\Models\Persona;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServidoresRelationManager extends RelationManager
{
    protected static string $relationship = 'servidores';

    protected static ?string $title = 'Maestros / servidores';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('rol')
                ->label('Rol en el aula')
                ->placeholder('Maestro, ayudante, servidor')
                ->maxLength(100),
            DatePicker::make('fecha_inicio')
                ->label('Fecha de inicio')
                ->native(false),
            DatePicker::make('fecha_fin')
                ->label('Fecha de fin')
                ->native(false),
            Toggle::make('activo')
                ->default(true)
                ->required(),
            Textarea::make('observaciones')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('persona.apellido')
            ->columns([
                TextColumn::make('persona.id')
                    ->label('Persona ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('persona.apellido')
                    ->label('Apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),
                TextColumn::make('persona.nombre')
                    ->label('Nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->placeholder('-'),
                IconColumn::make('activo')
                    ->boolean(),
                TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date(),
                TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date(),
            ])
            ->headerActions([
                Action::make('agregarServidor')
                    ->label('Agregar maestro / servidor')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Select::make('persona_id')
                            ->label('Persona')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Persona::query()
                                ->buscarPorNombreApellido($search)
                                ->orderBy('apellido')
                                ->orderBy('nombre')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Persona $persona): array => [
                                    $persona->id => $this->personaLabel($persona),
                                ])
                                ->all())
                            ->getOptionLabelUsing(function ($value): ?string {
                                $persona = $value ? Persona::query()->find($value) : null;

                                return $persona ? $this->personaLabel($persona) : null;
                            })
                            ->required(),
                        TextInput::make('rol')
                            ->label('Rol en el aula')
                            ->placeholder('Maestro, ayudante, servidor')
                            ->maxLength(100),
                        DatePicker::make('fecha_inicio')
                            ->label('Fecha de inicio')
                            ->default(now()->toDateString())
                            ->native(false),
                    ])
                    ->action(fn (array $data): mixed => $this->agregarServidor($data)),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('quitarServidor')
                    ->label('Quitar')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar maestro / servidor')
                    ->modalDescription('La persona dejará de tener acceso operativo a esta aula como servidor IPN.')
                    ->action(fn (IpnAulaServidor $record): mixed => $this->quitarServidor($record)),
            ])
            ->toolbarActions([]);
    }

    protected function agregarServidor(array $data): void
    {
        $aulaId = (int) $this->getOwnerRecord()->id;
        $personaId = (int) $data['persona_id'];

        $servidor = IpnAulaServidor::query()
            ->where('ipn_aula_id', $aulaId)
            ->where('persona_id', $personaId)
            ->first();

        if ($servidor) {
            $servidor->update([
                'rol' => $data['rol'] ?? $servidor->rol,
                'fecha_inicio' => $data['fecha_inicio'] ?? $servidor->fecha_inicio,
                'fecha_fin' => null,
                'activo' => true,
            ]);
        } else {
            IpnAulaServidor::create([
                'ipn_aula_id' => $aulaId,
                'persona_id' => $personaId,
                'rol' => $data['rol'] ?? null,
                'fecha_inicio' => $data['fecha_inicio'] ?? now()->toDateString(),
                'activo' => true,
            ]);
        }

        Notification::make()
            ->title('Servidor agregado al aula')
            ->success()
            ->send();
    }

    protected function quitarServidor(IpnAulaServidor $record): void
    {
        $record->update([
            'activo' => false,
            'fecha_fin' => now()->toDateString(),
        ]);

        Notification::make()
            ->title('Servidor quitado del aula')
            ->success()
            ->send();
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->id} - {$persona->apellido} {$persona->nombre}").($persona->telefono ? " ({$persona->telefono})" : '');
    }
}
