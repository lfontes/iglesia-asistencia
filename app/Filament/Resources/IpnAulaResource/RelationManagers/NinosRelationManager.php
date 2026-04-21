<?php

namespace App\Filament\Resources\IpnAulaResource\RelationManagers;

use App\Models\IpnAsistencia;
use App\Models\IpnAulaPersona;
use App\Models\Persona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NinosRelationManager extends RelationManager
{
    protected static string $relationship = 'participaciones';

    protected static ?string $title = 'Niños del aula';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('fecha_inicio')
                ->label('Fecha de inicio')
                ->native(false),
            Forms\Components\DatePicker::make('fecha_fin')
                ->label('Fecha de fin')
                ->native(false),
            Forms\Components\Toggle::make('activo')
                ->default(true)
                ->required(),
            Forms\Components\Textarea::make('observaciones')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('persona.apellido')
            ->columns([
                Tables\Columns\TextColumn::make('persona.id')
                    ->label('Persona ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('persona.apellido')
                    ->label('Apellido')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),
                Tables\Columns\TextColumn::make('persona.nombre')
                    ->label('Nombre')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'persona',
                        fn (Builder $personaQuery) => $personaQuery->buscarPorNombreApellido($search)
                    ))
                    ->sortable(),
                Tables\Columns\TextColumn::make('persona.edad')
                    ->label('Edad')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} años" : '-'),
                Tables\Columns\TextColumn::make('persona.responsablePersona.apellido')
                    ->label('Responsable')
                    ->formatStateUsing(fn ($state, IpnAulaPersona $record): string => $record->persona?->responsableIpnLabel() ?: '-')
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date(),
                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('agregarNino')
                    ->label('Agregar niño existente')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('persona_id')
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
                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Fecha de inicio')
                            ->default(now()->toDateString())
                            ->native(false),
                        Forms\Components\Textarea::make('observaciones')
                            ->rows(3),
                    ])
                    ->action(fn (array $data): mixed => $this->agregarNinoExistente($data)),

                Tables\Actions\Action::make('crearNino')
                    ->label('Crear niño')
                    ->icon('heroicon-o-face-smile')
                    ->form([
                        Forms\Components\TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('apellido')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('fecha_nacimiento')
                            ->label('Fecha de nacimiento')
                            ->native(false),
                        Forms\Components\Select::make('responsable_persona_id')
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('quitarDelAula')
                    ->label('Quitar del aula')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar niño del aula')
                    ->modalDescription('Si no tiene asistencias en esta aula, se elimina la relación. Si tiene historial, se cierra la participación.')
                    ->action(fn (IpnAulaPersona $record): mixed => $this->quitarDelAula($record)),
            ])
            ->bulkActions([]);
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

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->id} - {$persona->apellido} {$persona->nombre}");
    }
}
