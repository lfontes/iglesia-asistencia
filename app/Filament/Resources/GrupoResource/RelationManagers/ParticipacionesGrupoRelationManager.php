<?php

namespace App\Filament\Resources\GrupoResource\RelationManagers;

use App\Models\AsistenciaGrupo;
use App\Models\ParticipacionGrupo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipacionesGrupoRelationManager extends RelationManager
{
    protected static string $relationship = 'participacionesGrupo';

    protected static ?string $title = 'Personas Participantes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('rol_grupo_id')
                ->label('Rol')
                ->relationship('rolGrupo', 'nombre')
                ->placeholder('Sin rol')
                ->searchable()
                ->preload(),
            Forms\Components\Toggle::make('recibe_recordatorios')
                ->label('Recibe recordatorios')
                ->helperText('Usa esta marca para indicar qué facilitador debe recibir el recordatorio principal del grupo.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('persona.apellido')
            ->columns([
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

                Tables\Columns\TextColumn::make('rolGrupo.nombre')
                    ->label('Rol')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('recibe_recordatorios')
                    ->label('Recordatorios')
                    ->boolean(),

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rol_grupo_id')
                    ->label('Rol')
                    ->relationship('rolGrupo', 'nombre'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('eliminarDelGrupo')
                    ->label('Eliminar del grupo')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar persona del grupo')
                    ->modalDescription('Solo se eliminará la participación si la persona no tiene asistencias presentes registradas en este grupo.')
                    ->action(function (ParticipacionGrupo $record): void {
                        $tieneAsistenciasPresentes = AsistenciaGrupo::query()
                            ->where('grupo_id', $record->grupo_id)
                            ->where('persona_id', $record->persona_id)
                            ->where('presente', true)
                            ->exists();

                        if ($tieneAsistenciasPresentes) {
                            Notification::make()
                                ->title('No se puede eliminar esta persona del grupo')
                                ->body('Tiene asistencias presentes registradas en este grupo. Para conservar el historial, no se permite eliminar la participación.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->title('Persona eliminada del grupo')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
