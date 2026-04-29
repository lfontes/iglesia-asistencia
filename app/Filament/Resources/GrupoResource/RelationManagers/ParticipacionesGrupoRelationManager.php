<?php

namespace App\Filament\Resources\GrupoResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Models\AsistenciaGrupo;
use App\Models\ParticipacionGrupo;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipacionesGrupoRelationManager extends RelationManager
{
    protected static string $relationship = 'participacionesGrupo';

    protected static ?string $title = 'Personas Participantes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('rol_grupo_id')
                ->label('Rol')
                ->relationship('rolGrupo', 'nombre')
                ->placeholder('Sin rol')
                ->searchable()
                ->preload(),
            Toggle::make('recibe_recordatorios')
                ->label('Recibe recordatorios')
                ->helperText('Usa esta marca para indicar qué facilitador debe recibir el recordatorio principal del grupo.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('persona.apellido')
            ->columns([
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

                TextColumn::make('rolGrupo.nombre')
                    ->label('Rol')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('recibe_recordatorios')
                    ->label('Recordatorios')
                    ->boolean(),

                TextColumn::make('fecha_inicio')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),

                TextColumn::make('fecha_fin')
                    ->label('Fin')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rol_grupo_id')
                    ->label('Rol')
                    ->relationship('rolGrupo', 'nombre'),
            ])
            ->headerActions([])
            ->recordActions([
                EditAction::make(),
                Action::make('eliminarDelGrupo')
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
            ->toolbarActions([]);
    }
}
