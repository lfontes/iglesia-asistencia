<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\EventoFechaResource\Pages\ListEventoFechas;
use App\Filament\Resources\EventoFechaResource\Pages\CreateEventoFecha;
use App\Filament\Resources\EventoFechaResource\Pages\EditEventoFecha;
use App\Filament\Resources\EventoFechaResource\Pages\TomarAsistencia;
use App\Filament\Resources\EventoFechaResource\Pages;
use App\Filament\Resources\EventoFechaResource\RelationManagers\AsistenciasRelationManager;
use App\Filament\Resources\EventoFechaResource\RelationManagers\InscripcionesRelationManager;
use App\Jobs\SendEventoReminderBatchJob;
use App\Models\EventoFecha;
use App\Models\EventoInscripcion;
use App\Models\WhatsAppMessage;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventoFechaResource extends Resource
{
    protected static ?string $model = EventoFecha::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Fechas de evento';

    protected static ?string $modelLabel = 'fecha de evento';

    protected static ?string $pluralModelLabel = 'fechas de evento';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('evento_id')
                    ->relationship('evento', 'nombre')
                    ->required()
                    ->searchable(),

                DatePicker::make('fecha')
                    ->required(),

                Textarea::make('observaciones')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('evento.nombre')
                    ->label('Evento')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('fecha')
                    ->date()
                    ->sortable(),

                TextColumn::make('asistencias_count')
                    ->counts('asistencias')
                    ->label('Asistentes'),
            ])
            ->defaultSort('fecha', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('asistencia')
                    ->label('Tomar Asistencia')
                    ->url(fn ($record) => EventoFechaResource::getUrl('asistencia', [
                        'record' => $record,
                    ]))
                    ->icon('heroicon-o-check'),
                Action::make('inscripcion_publica')
                    ->label('Formulario público')
                    ->url(fn (EventoFecha $record): string => $record->publicInscriptionUrl())
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                Action::make('recordatorio_whatsapp')
                    ->label('Recordatorio WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar recordatorio por WhatsApp')
                    ->modalDescription(fn (EventoFecha $record): string => static::buildRecordatorioDescription($record))
                    ->action(fn (EventoFecha $record) => static::enviarRecordatorioWhatsapp($record)),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);

    }

    public static function getRelations(): array
    {

        return [
            InscripcionesRelationManager::class,
            AsistenciasRelationManager::class,
        ];

    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventoFechas::route('/'),
            'create' => CreateEventoFecha::route('/create'),
            'edit' => EditEventoFecha::route('/{record}/edit'),
            'asistencia' => TomarAsistencia::route('/{record}/asistencia'),
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

        return $user?->hasRole(['facilitador', 'lider', 'coordinador_grupos']) && ! $user->hasRole('admin');
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
            ."Inscriptos: {$stats['inscriptos']}.\n"
            ."Con teléfono: {$stats['con_telefono']}.\n"
            ."Sin teléfono: {$stats['sin_telefono']}.\n"
            ."Ya enviados hoy: {$stats['ya_enviados']}.\n"
            ."Se enviarán ahora: {$stats['a_enviar']}.";
    }

    protected static function enviarRecordatorioWhatsapp(EventoFecha $eventoFecha): void
    {
        $stats = static::getReminderStats($eventoFecha);

        if ($stats['a_enviar'] === 0) {
            Notification::make()
                ->title('No hay recordatorios pendientes')
                ->body('No hay inscriptos nuevos con teléfono válido para enviar hoy.')
                ->color('gray')
                ->send();

            return;
        }

        SendEventoReminderBatchJob::dispatch($eventoFecha->id, auth()->id());

        Notification::make()
            ->title('Recordatorio en cola')
            ->body("Se encoló el envío para {$stats['a_enviar']} inscriptos.")
            ->color('success')
            ->send();
    }
}
