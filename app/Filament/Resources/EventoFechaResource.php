<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoFechaResource\Pages;
use App\Filament\Resources\EventoFechaResource\RelationManagers\AsistenciasRelationManager;
use App\Models\EventoFecha;
use App\Models\EventoInscripcion;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;

class EventoFechaResource extends Resource
{
    protected static ?string $model = EventoFecha::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Fechas de evento';

    protected static ?string $modelLabel = 'fecha de evento';

    protected static ?string $pluralModelLabel = 'fechas de evento';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('evento_id')
                    ->relationship('evento', 'nombre')
                    ->required()
                    ->searchable(),

                Forms\Components\DatePicker::make('fecha')
                    ->required(),

                Forms\Components\Textarea::make('observaciones')
                    ->columnSpanFull(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('evento.nombre')
                    ->label('Evento')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('asistencias_count')
                    ->counts('asistencias')
                    ->label('Asistentes'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('asistencia')
                    ->label('Tomar Asistencia')
                    ->url(fn ($record) => EventoFechaResource::getUrl('asistencia', [
                        'record' => $record,
                    ]))
                    ->icon('heroicon-o-check'),
                Tables\Actions\Action::make('inscripcion_publica')
                    ->label('Formulario público')
                    ->url(fn ($record) => route('eventos.inscripcion.create', $record))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                Tables\Actions\Action::make('recordatorio_whatsapp')
                    ->label('Recordatorio WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar recordatorio por WhatsApp')
                    ->modalDescription(fn (EventoFecha $record): string => static::buildRecordatorioDescription($record))
                    ->action(fn (EventoFecha $record) => static::enviarRecordatorioWhatsapp($record)),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
            
    }


    public static function getRelations(): array
    {
        
           return [
        AsistenciasRelationManager::class,
    ];
        
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventoFechas::route('/'),
            'create' => Pages\CreateEventoFecha::route('/create'),
            'edit' => Pages\EditEventoFecha::route('/{record}/edit'),
            'asistencia' => Pages\TomarAsistencia::route('/{record}/asistencia'),
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

        return $user?->hasRole(['facilitador', 'lider']) && ! $user->hasRole('admin');
    }

    /**
     * @return array{inscriptos:int,con_telefono:int,sin_telefono:int,ya_enviados:int,a_enviar:int}
     */
    protected static function getReminderStats(EventoFecha $eventoFecha): array
    {
        $inscripciones = $eventoFecha->inscripciones()
            ->with('persona:id,telefono,telefono_normalizado')
            ->where('estado', 'inscripto')
            ->get();

        $conTelefono = $inscripciones
            ->filter(fn (EventoInscripcion $inscripcion): bool => filled($inscripcion->persona?->telefono_normalizado ?: $inscripcion->persona?->telefono))
            ->values();

        $enviadosHoy = WhatsAppMessage::query()
            ->where('use_case', 'recordatorio_evento')
            ->where('evento_fecha_id', $eventoFecha->id)
            ->whereDate('created_at', today())
            ->pluck('persona_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $aEnviar = $conTelefono
            ->reject(fn (EventoInscripcion $inscripcion): bool => $enviadosHoy->contains((int) $inscripcion->persona_id))
            ->count();

        return [
            'inscriptos' => $inscripciones->count(),
            'con_telefono' => $conTelefono->count(),
            'sin_telefono' => $inscripciones->count() - $conTelefono->count(),
            'ya_enviados' => $enviadosHoy->count(),
            'a_enviar' => $aEnviar,
        ];
    }

    protected static function buildRecordatorioDescription(EventoFecha $eventoFecha): string
    {
        $stats = static::getReminderStats($eventoFecha);

        return "Se enviará un recordatorio por WhatsApp a los inscriptos con teléfono válido.\n\n"
            . "Inscriptos: {$stats['inscriptos']}.\n"
            . "Con teléfono: {$stats['con_telefono']}.\n"
            . "Sin teléfono: {$stats['sin_telefono']}.\n"
            . "Ya enviados hoy: {$stats['ya_enviados']}.\n"
            . "Se enviarán ahora: {$stats['a_enviar']}.";
    }

    protected static function enviarRecordatorioWhatsapp(EventoFecha $eventoFecha): void
    {
        $inscripciones = $eventoFecha->inscripciones()
            ->with('persona')
            ->where('estado', 'inscripto')
            ->get();

        $enviadosHoy = WhatsAppMessage::query()
            ->where('use_case', 'recordatorio_evento')
            ->where('evento_fecha_id', $eventoFecha->id)
            ->whereDate('created_at', today())
            ->pluck('persona_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $enviados = 0;
        $omitidos = 0;
        $fallidos = 0;
        $detalles = [];

        foreach ($inscripciones as $inscripcion) {
            $persona = $inscripcion->persona;

            if (! $persona) {
                $omitidos++;
                $detalles[] = 'Inscripción sin persona asociada.';
                continue;
            }

            if (blank($persona->telefono_normalizado ?: $persona->telefono)) {
                $omitidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': sin teléfono válido.';
                continue;
            }

            if ($enviadosHoy->contains((int) $persona->id)) {
                $omitidos++;
                continue;
            }

            try {
                app(WhatsAppService::class)->sendEventReminder($eventoFecha->loadMissing('evento'), $persona);
                $enviados++;
            } catch (RequestException $exception) {
                $fallidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': '
                    . (string) ($exception->response?->json('error.message') ?? $exception->getMessage());
            } catch (\RuntimeException $exception) {
                $omitidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': ' . $exception->getMessage();
            } catch (\Throwable $exception) {
                $fallidos++;
                $detalles[] = trim("{$persona->nombre} {$persona->apellido}") . ': ' . $exception->getMessage();
            }
        }

        $body = "Enviados: {$enviados}. Omitidos: {$omitidos}. Fallidos: {$fallidos}.";

        if ($detalles !== []) {
            $body .= "\n" . implode("\n", array_slice($detalles, 0, 5));

            if (count($detalles) > 5) {
                $body .= "\n...y " . (count($detalles) - 5) . ' más.';
            }
        }

        Notification::make()
            ->title($fallidos > 0 ? 'Recordatorio enviado con observaciones' : 'Recordatorio enviado')
            ->body($body)
            ->color($fallidos > 0 ? 'warning' : 'success')
            ->send();
    }
}
