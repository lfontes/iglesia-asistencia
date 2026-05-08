<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
use App\Models\WhatsAppBulkDispatch;
use App\Models\WhatsAppMessage;
use App\Services\AsistenciasPendientesService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AsistenciasPendientes extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string | \UnitEnum | null $navigationGroup = 'Asistencia';

    protected static ?string $navigationLabel = 'Asistencias pendientes';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Asistencias pendientes';

    protected string $view = 'filament.pages.asistencias-pendientes';

    public ?string $fecha = null;

    /** @var array<string, array<string, mixed>|null> */
    protected array $lastReminderStatuses = [];

    protected ?WhatsAppBulkDispatch $bulkDispatchStatus = null;

    protected ?Collection $pendientesCache = null;

    protected ?string $pendientesCacheKey = null;

    public function mount(): void
    {
        $this->form->fill([
            'fecha' => $this->fecha ?? now()->toDateString(),
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canViewAsistenciasPendientes() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fecha')
                ->label('Fecha de referencia')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->live()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->emptyStateHeading('Sin asistencias pendientes')
            ->emptyStateDescription('No hay grupos con asistencia pendiente para la fecha de referencia seleccionada.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Grupo')
                    ->state(fn (Grupo $record): string => (string) ($this->getPendienteDataForGrupo((int) $record->id)['grupo'] ?? $record->nombre))
                    ->url(fn (Grupo $record): string => ResumenAsistenciaGrupos::getUrl(['grupo_id' => $record->id]))
                    ->color('primary')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('frecuencia')
                    ->label('Frecuencia')
                    ->state(fn (Grupo $record): string => $this->formatFrecuencia(
                        (string) ($this->getPendienteDataForGrupo((int) $record->id)['frecuencia'] ?? '')
                    ))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('periodo')
                    ->label('Período evaluado')
                    ->state(function (Grupo $record): string {
                        $item = $this->getPendienteDataForGrupo((int) $record->id);

                        if (! $item) {
                            return '-';
                        }

                        return Carbon::parse((string) $item['periodo_inicio'])->format('d/m/Y')
                            .' - '.
                            Carbon::parse((string) $item['periodo_fin'])->format('d/m/Y');
                    }),
                TextColumn::make('ultima_asistencia')
                    ->label('Última asistencia')
                    ->state(function (Grupo $record): string {
                        $ultimaAsistencia = $this->getPendienteDataForGrupo((int) $record->id)['ultima_asistencia'] ?? null;

                        return $ultimaAsistencia
                            ? Carbon::parse((string) $ultimaAsistencia)->format('d/m/Y')
                            : 'Sin asistencias registradas';
                    }),
                ViewColumn::make('facilitadores')
                    ->label('Facilitadores')
                    ->view('filament.tables.columns.asistencias-pendientes-facilitadores'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hoy')
                ->label('Usar hoy')
                ->color('gray')
                ->action(function (): void {
                    $this->fecha = now()->toDateString();
                    $this->resetPendientesState();
                    $this->form->fill(['fecha' => $this->fecha]);
                    $this->resetTable();
                }),
            Action::make('semanaAnterior')
                ->label('Semana anterior')
                ->color('gray')
                ->action(function (): void {
                    $this->fecha = now()->endOfWeek(Carbon::SUNDAY)->toDateString();
                    $this->resetPendientesState();
                    $this->form->fill(['fecha' => $this->fecha]);
                    $this->resetTable();
                }),
            Action::make('enviarRecordatoriosTodos')
                ->label('Enviar recordatorios por WhatsApp')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->disabled(fn (): bool => $this->estaBloqueadoEnvioMasivo())
                ->requiresConfirmation()
                ->modalHeading('Enviar recordatorios a todos')
                ->modalDescription('Se enviará un único recordatorio por grupo al facilitador marcado para recordatorios o, si no aplica, al primer facilitador con teléfono válido.')
                ->action(fn (): Notification => $this->enviarRecordatoriosATodos()),
        ];
    }

    public function updatedFecha(): void
    {
        $this->resetPendientesState();
        $this->resetTable();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getPendientes(): Collection
    {
        $cacheKey = $this->makePendientesCacheKey();

        if ($this->pendientesCache !== null && $this->pendientesCacheKey === $cacheKey) {
            return $this->pendientesCache;
        }

        $this->pendientesCache = app(AsistenciasPendientesService::class)
            ->obtener($this->getFechaReferencia());
        $this->pendientesCacheKey = $cacheKey;

        return $this->pendientesCache;
    }

    public function enviarRecordatorioPlantilla(int $grupoId, int $personaId): void
    {
        $item = $this->getPendientes()->firstWhere('grupo_id', $grupoId);

        if (! $item) {
            Notification::make()
                ->title('No se encontró el grupo pendiente')
                ->danger()
                ->send();

            return;
        }

        $facilitador = collect($item['facilitadores'])->firstWhere('persona_id', $personaId);

        if (! $facilitador) {
            Notification::make()
                ->title('No se encontró el facilitador')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = $this->enviarRecordatorioItem($item, $facilitador);

            $this->lastReminderStatuses = [];

            Notification::make()
                ->title('Recordatorio enviado a Meta')
                ->body("Facilitador: {$result['nombre']}")
                ->success()
                ->send();
        } catch (RequestException $exception) {
            $metaMessage = $exception->response?->json('error.message') ?? $exception->getMessage();

            Notification::make()
                ->title('No se pudo enviar el recordatorio')
                ->body((string) $metaMessage)
                ->danger()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Error al preparar el recordatorio')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function enviarRecordatoriosATodos(): Notification
    {
        if ($this->estaBloqueadoEnvioMasivo()) {
            $dispatch = $this->getBulkDispatchStatusActual();

            return Notification::make()
                ->title('El envío masivo ya fue realizado para este período')
                ->body($dispatch?->created_at?->format('d/m/Y H:i') ?? 'No hace falta reenviarlo todavía.')
                ->warning()
                ->send();
        }

        $enviados = 0;
        $omitidos = 0;
        $fallidos = 0;
        $detalles = [];

        foreach ($this->getPendientes() as $item) {
            $facilitador = $this->resolverDestinatarioRecordatorio($item);

            if ($facilitador === null) {
                $omitidos++;
                $detalles[] = $this->buildErrorDetalle((string) $item['grupo'], 'Sin facilitador elegible para el envío.');

                continue;
            }

            try {
                $this->enviarRecordatorioItem($item, $facilitador);
                $enviados++;
            } catch (RequestException $exception) {
                $fallidos++;
                $detalles[] = $this->buildErrorDetalle(
                    (string) $facilitador['nombre'],
                    (string) ($exception->response?->json('error.message') ?? $exception->getMessage()),
                );
            } catch (RuntimeException $exception) {
                $omitidos++;
                $detalles[] = $this->buildErrorDetalle((string) $facilitador['nombre'], $exception->getMessage());
            } catch (Throwable $exception) {
                $fallidos++;
                $detalles[] = $this->buildErrorDetalle((string) $facilitador['nombre'], $exception->getMessage());
            }
        }

        $this->lastReminderStatuses = [];

        if ($enviados > 0) {
            WhatsAppBulkDispatch::query()->create([
                'use_case' => 'recordatorio_asistencia_grupo',
                'fecha_referencia' => $this->getFechaReferencia()->toDateString(),
                'period_hash' => $this->getCurrentPeriodHash(),
                'period_summary' => $this->getCurrentPeriodSummary(),
                'user_id' => auth()->id(),
                'sent_count' => $enviados,
                'skipped_count' => $omitidos,
                'failed_count' => $fallidos,
                'meta' => [
                    'total_grupos' => $this->getPendientes()->count(),
                    'detalles' => $detalles,
                ],
            ]);
        }

        $this->bulkDispatchStatus = null;

        $body = "Enviados: {$enviados}. Omitidos: {$omitidos}. Fallidos: {$fallidos}.";

        if ($detalles !== []) {
            $body .= "\n".implode("\n", array_slice($detalles, 0, 5));

            if (count($detalles) > 5) {
                $body .= "\n...y ".(count($detalles) - 5).' más.';
            }
        }

        return Notification::make()
            ->title($fallidos > 0 ? 'Envío masivo finalizado con observaciones' : 'Envío masivo finalizado')
            ->body($body)
            ->color($fallidos > 0 ? 'warning' : 'success')
            ->send();
    }

    public function getBulkDispatchStatusLabel(): ?string
    {
        $dispatch = $this->getBulkDispatchStatusActual();

        if (! $dispatch) {
            return null;
        }

        $userName = trim((string) ($dispatch->user?->name ?? ''));
        $when = $dispatch->created_at?->format('d/m/Y H:i');
        $summary = $dispatch->period_summary ? "Período: {$dispatch->period_summary}. " : '';
        $by = $userName !== '' ? "Por: {$userName}. " : '';

        return trim("Ya se enviaron recordatorios masivos. {$summary}{$by}Fecha: {$when}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPendienteDataForGrupo(int $grupoId): ?array
    {
        return $this->getPendientes()->firstWhere('grupo_id', $grupoId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFacilitadoresPendientesParaGrupo(int $grupoId): array
    {
        return (array) ($this->getPendienteDataForGrupo($grupoId)['facilitadores'] ?? []);
    }

    public function formatFrecuencia(string $frecuencia): string
    {
        return match ($frecuencia) {
            Grupo::FRECUENCIA_SEMANAL => 'Semanal',
            Grupo::FRECUENCIA_QUINCENAL => 'Quincenal',
            Grupo::FRECUENCIA_MENSUAL => 'Mensual',
            default => Str::title($frecuencia),
        };
    }

    /**
     * @return array{total_grupos:int,total_facilitadores:int,sin_telefono:int,semanales:int,quincenales:int,mensuales:int}
     */
    public function getSummary(): array
    {
        $pendientes = $this->getPendientes();

        return [
            'total_grupos' => $pendientes->count(),
            'total_facilitadores' => $pendientes->sum(fn (array $item): int => count($item['facilitadores'])),
            'sin_telefono' => $pendientes->sum(
                fn (array $item): int => collect($item['facilitadores'])->filter(fn (array $f): bool => blank($f['telefono']))->count()
            ),
            'semanales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_SEMANAL)->count(),
            'quincenales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_QUINCENAL)->count(),
            'mensuales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_MENSUAL)->count(),
        ];
    }

    protected function getFechaReferencia(): Carbon
    {
        try {
            return filled($this->fecha)
                ? Carbon::parse((string) $this->fecha)->startOfDay()
                : now()->startOfDay();
        } catch (Throwable) {
            return now()->startOfDay();
        }
    }

    protected function getTableQuery(): Builder
    {
        $ids = $this->getPendientes()
            ->pluck('grupo_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return Grupo::query()->whereRaw('1 = 0');
        }

        return Grupo::query()
            ->whereKey($ids)
            ->orderBy('nombre');
    }

    protected function estaBloqueadoEnvioMasivo(): bool
    {
        return $this->getBulkDispatchStatusActual() !== null;
    }

    protected function getBulkDispatchStatusActual(): ?WhatsAppBulkDispatch
    {
        if ($this->bulkDispatchStatus !== null) {
            return $this->bulkDispatchStatus;
        }

        $periodHash = $this->getCurrentPeriodHash();

        if ($periodHash === null) {
            return null;
        }

        return $this->bulkDispatchStatus = WhatsAppBulkDispatch::query()
            ->with('user:id,name')
            ->where('use_case', 'recordatorio_asistencia_grupo')
            ->where('period_hash', $periodHash)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{status:string,updated_at:?string,error_message:?string}|null
     */
    public function getReminderStatus(int $grupoId, int $personaId, string $periodoInicio, string $periodoFin): ?array
    {
        $cacheKey = implode('|', [$grupoId, $personaId, $periodoInicio, $periodoFin]);

        if (array_key_exists($cacheKey, $this->lastReminderStatuses)) {
            return $this->lastReminderStatuses[$cacheKey];
        }

        $message = WhatsAppMessage::query()
            ->where('use_case', 'recordatorio_asistencia_grupo')
            ->where('grupo_id', $grupoId)
            ->where('persona_id', $personaId)
            ->whereDate('periodo_inicio', $periodoInicio)
            ->whereDate('periodo_fin', $periodoFin)
            ->latest('id')
            ->first();

        $status = $message
            ? [
                'status' => (string) $message->status,
                'updated_at' => $message->updated_at?->format('d/m/Y H:i'),
                'error_message' => $message->error_message,
            ]
            : null;

        $this->lastReminderStatuses[$cacheKey] = $status;

        return $status;
    }

    /**
     * @param  array<string, mixed>  $facilitador
     */
    public function facilitadorTieneTelefonoWhatsappValido(array $facilitador): bool
    {
        return $this->normalizarTelefonoWhatsapp(
            (string) ($facilitador['telefono_normalizado'] ?: $facilitador['telefono'] ?: '')
        ) !== null;
    }

    protected function normalizarTelefonoWhatsapp(string $telefono): ?string
    {
        $digits = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '54') && ! Str::startsWith($digits, '549')) {
            return $digits;
        }

        if (Str::startsWith($digits, '549')) {
            return '54'.substr($digits, 3);
        }

        return $digits;
    }

    protected function normalizarNombreFacilitador(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return 'facilitador';
        }

        $parts = preg_split('/\s+/', $nombre) ?: [];

        return Str::title((string) end($parts));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $facilitador
     * @return array{nombre:string}
     */
    protected function enviarRecordatorioItem(array $item, array $facilitador): array
    {
        $telefono = $this->normalizarTelefonoWhatsapp(
            (string) ($facilitador['telefono_normalizado'] ?: $facilitador['telefono'] ?: '')
        );

        if ($telefono === null) {
            throw new RuntimeException('Sin teléfono válido.');
        }

        $nombreFacilitador = $this->normalizarNombreFacilitador((string) $facilitador['nombre']);
        $periodo = Carbon::parse($item['periodo_inicio'])->format('d/m/Y').' al '.Carbon::parse($item['periodo_fin'])->format('d/m/Y');
        $url = AsistenciaGruposCrecimiento::getUrl();
        $renderedBody = "Hola {$nombreFacilitador}, te recordamos cargar la asistencia del grupo {$item['grupo']} correspondiente al período {$periodo}.\n\nPuedes hacerlo aquí:\n{$url}";

        app(WhatsAppService::class)->sendTemplateWithContext(
            $telefono,
            'recordatorio_asistencia_grupo',
            [
                $nombreFacilitador,
                (string) $item['grupo'],
                $periodo,
                $url,
            ],
            [
                'persona_id' => $facilitador['persona_id'],
                'grupo_id' => $item['grupo_id'],
                'use_case' => 'recordatorio_asistencia_grupo',
                'periodo_inicio' => (string) $item['periodo_inicio'],
                'periodo_fin' => (string) $item['periodo_fin'],
            ],
            $renderedBody,
        );

        return ['nombre' => $nombreFacilitador];
    }

    protected function buildErrorDetalle(string $nombre, string $detalle): string
    {
        $nombre = trim($nombre) !== '' ? $nombre : 'Facilitador';
        $detalle = trim($detalle) !== '' ? $detalle : 'Sin detalle';

        return $nombre.': '.$detalle;
    }

    protected function getCurrentPeriodHash(): ?string
    {
        $periods = $this->getPendientes()
            ->map(fn (array $item): string => implode('|', [
                (string) $item['periodo_inicio'],
                (string) $item['periodo_fin'],
            ]))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($periods === []) {
            return null;
        }

        return hash('sha256', implode('::', $periods));
    }

    protected function getCurrentPeriodSummary(): ?string
    {
        $periods = $this->getPendientes()
            ->map(fn (array $item): string => Carbon::parse($item['periodo_inicio'])->format('d/m/Y').' al '.Carbon::parse($item['periodo_fin'])->format('d/m/Y'))
            ->unique()
            ->values()
            ->all();

        if ($periods === []) {
            return null;
        }

        return implode(' | ', $periods);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function resolverDestinatarioRecordatorio(array $item): ?array
    {
        $facilitadores = collect($item['facilitadores'] ?? [])
            ->filter(fn (array $facilitador): bool => filled($facilitador['persona_id']));

        $principal = $facilitadores
            ->first(fn (array $facilitador): bool => (bool) ($facilitador['recibe_recordatorios'] ?? false) && $this->facilitadorTieneTelefonoWhatsappValido($facilitador));

        if ($principal) {
            return $principal;
        }

        return $facilitadores
            ->first(fn (array $facilitador): bool => $this->facilitadorTieneTelefonoWhatsappValido($facilitador));
    }

    protected function resetPendientesState(): void
    {
        $this->pendientesCache = null;
        $this->pendientesCacheKey = null;
        $this->lastReminderStatuses = [];
        $this->bulkDispatchStatus = null;
    }

    protected function makePendientesCacheKey(): string
    {
        return (string) ($this->fecha ?? '');
    }
}
