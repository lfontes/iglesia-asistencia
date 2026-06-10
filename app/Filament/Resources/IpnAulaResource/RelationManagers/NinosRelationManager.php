<?php

namespace App\Filament\Resources\IpnAulaResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Actions\EditAction;
use App\Models\IpnAsistencia;
use App\Models\IpnAulaPersona;
use App\Models\Persona;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NinosRelationManager extends RelationManager
{
    protected static string $relationship = 'participaciones';

    protected static ?string $title = 'Niños del aula';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
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
                    ->action($this->editarNinoAction())
                    ->sortable(),
                TextColumn::make('persona.nombre')
                    ->label('Nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->action($this->editarNinoAction())
                    ->sortable(),
                TextColumn::make('persona.edad')
                    ->label('Edad')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} años" : '-'),
                TextColumn::make('persona.responsablePersona.apellido')
                    ->label('Responsable')
                    ->formatStateUsing(fn ($state, IpnAulaPersona $record): string => $record->persona?->responsableIpnLabel() ?: '-')
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
                Action::make('agregarNino')
                    ->label('Agregar niño existente')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Select::make('persona_id')
                            ->label('Niño')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Persona::query()
                                ->where('es_menor', true)
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
                        DatePicker::make('fecha_inicio')
                            ->label('Fecha de inicio')
                            ->default(now()->toDateString())
                            ->native(false),
                        Textarea::make('observaciones')
                            ->rows(3),
                    ])
                    ->action(fn (array $data): mixed => $this->agregarNinoExistente($data)),

                Action::make('crearNino')
                    ->label('Crear niño')
                    ->icon('heroicon-o-face-smile')
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
                        Select::make('responsable_persona_id')
                            ->label('Responsable / tutor')
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
                            }),
                    ])
                    ->action(fn (array $data): mixed => $this->crearNinoEnAula($data)),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('quitarDelAula')
                    ->label('Quitar del aula')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar niño del aula')
                    ->modalDescription('Si no tiene asistencias en esta aula, se elimina la relación. Si tiene historial, se cierra la participación.')
                    ->action(fn (IpnAulaPersona $record): mixed => $this->quitarDelAula($record)),
            ])
            ->toolbarActions([]);
    }

    protected function agregarNinoExistente(array $data): void
    {
        $personaId = (int) $data['persona_id'];
        $aulaId = (int) $this->getOwnerRecord()->id;

        $existeActivo = IpnAulaPersona::query()
            ->where('ipn_aula_id', $aulaId)
            ->where('persona_id', $personaId)
            ->where('activo', true)
            ->where(function (Builder $query): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', now()->toDateString());
            })
            ->exists();

        if ($existeActivo) {
            Notification::make()
                ->title('El niño ya está activo en esta aula')
                ->warning()
                ->send();

            return;
        }

        IpnAulaPersona::create([
            'ipn_aula_id' => $aulaId,
            'persona_id' => $personaId,
            'fecha_inicio' => $data['fecha_inicio'] ?? now()->toDateString(),
            'activo' => true,
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        Notification::make()
            ->title('Niño agregado al aula')
            ->success()
            ->send();
    }

    protected function crearNinoEnAula(array $data): void
    {
        $persona = Persona::create([
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'responsable_persona_id' => $data['responsable_persona_id'] ?? null,
            'es_menor' => true,
        ]);

        IpnAulaPersona::create([
            'ipn_aula_id' => $this->getOwnerRecord()->id,
            'persona_id' => $persona->id,
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);

        Notification::make()
            ->title('Niño creado y agregado al aula')
            ->success()
            ->send();
    }

    protected function quitarDelAula(IpnAulaPersona $record): void
    {
        $tieneAsistencias = IpnAsistencia::query()
            ->where('ipn_aula_id', $record->ipn_aula_id)
            ->where('persona_id', $record->persona_id)
            ->exists();

        if ($tieneAsistencias) {
            $record->update([
                'activo' => false,
                'fecha_fin' => now()->toDateString(),
            ]);
        } else {
            $record->delete();
        }

        Notification::make()
            ->title($tieneAsistencias ? 'Participación cerrada' : 'Niño quitado del aula')
            ->success()
            ->send();
    }

    protected function editarNinoAction(): Action
    {
        return Action::make('editarNino')
            ->modalHeading(fn (IpnAulaPersona $record): string => "{$record->persona?->apellido}, {$record->persona?->nombre}")
            ->fillForm(fn (IpnAulaPersona $record): array => [
                'nombre' => $record->persona?->nombre ?? '',
                'apellido' => $record->persona?->apellido ?? '',
                'fecha_nacimiento' => $record->persona?->fecha_nacimiento?->format('Y-m-d'),
                'telefono' => $record->persona?->telefono,
                'email' => $record->persona?->email,
                'departamento' => $record->persona?->departamento,
                'responsable_persona_id' => $record->persona?->responsable_persona_id,
                'observaciones_ipn' => $record->persona?->observaciones_ipn,
            ])
            ->schema([
                TextInput::make('nombre')->required()->maxLength(255),
                TextInput::make('apellido')->required()->maxLength(255),
                DatePicker::make('fecha_nacimiento')
                    ->label('Fecha de nacimiento')
                    ->native(false),
                TextInput::make('telefono')
                    ->label('Teléfono del niño')
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
                Select::make('responsable_persona_id')
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
                            $persona->id => $this->personaLabel($persona),
                        ])
                        ->all())
                    ->getOptionLabelUsing(function ($value): ?string {
                        $persona = $value ? Persona::query()->find($value) : null;

                        return $persona ? $this->personaLabel($persona) : null;
                    })
                    ->helperText('Selecciona una persona existente de la base de datos.'),
                Textarea::make('observaciones_ipn')
                    ->label('Observaciones importantes')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->action(function (IpnAulaPersona $record, array $data): void {
                $record->persona?->update($data);
                Notification::make()->title('Datos del niño actualizados')->success()->send();
            });
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->id} - {$persona->apellido} {$persona->nombre}");
    }
}
