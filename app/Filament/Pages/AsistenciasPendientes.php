<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
use App\Filament\Pages\AsistenciaGruposCrecimiento;
use App\Models\WhatsAppMessage;
use App\Services\AsistenciasPendientesService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AsistenciasPendientes extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Grupos';

    protected static ?string $navigationLabel = 'Asistencias pendientes';

    protected static ?int $navigationSort = 18;

    protected static ?string $title = 'Asistencias pendientes';

    protected static string $view = 'filament.pages.asistencias-pendientes';

    public ?string $fecha = null;

    /** @var array<string, array<string, mixed>|null> */
    protected array $lastReminderStatuses = [];

    public function mount(): void
    {
        $this->form->fill([
            'fecha' => $this->fecha ?? now()->toDateString(),
        ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('fecha')
                ->label('Fecha de referencia')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->live()
                ->required(),
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
                    $this->form->fill(['fecha' => $this->fecha]);
                }),
            Action::make('semanaAnterior')
                ->label('Semana anterior')
                ->color('gray')
                ->action(function (): void {
                    $this->fecha = now()->endOfWeek(Carbon::SUNDAY)->toDateString();
                    $this->form->fill(['fecha' => $this->fecha]);
                }),
            Action::make('enviarPruebaWhatsapp')
                ->label('Enviar prueba WhatsApp')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono destino')
                        ->default(fn (): ?string => app(WhatsAppService::class)->getTestRecipient())
                        ->required(),
                    Forms\Components\Textarea::make('mensaje')
                        ->label('Mensaje')
                        ->rows(5)
                        ->default(function (): string {
                            $fecha = $this->getFechaReferencia()->format('d/m/Y');

                            return "Prueba de WhatsApp desde Iglesia de los Libres.\n\nFecha de referencia: {$fecha}.\nSi recibes este mensaje, la integración básica con Meta está funcionando.";
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $response = app(WhatsAppService::class)->sendText(
                            (string) $data['telefono'],
                            (string) $data['mensaje'],
                        );

                        $messageId = $response['messages'][0]['id'] ?? null;

                        Notification::make()
                            ->title('Mensaje de prueba enviado')
                            ->body($messageId ? "ID de Meta: {$messageId}" : 'Meta aceptó el envío de prueba.')
                            ->success()
                            ->send();
                    } catch (RequestException $exception) {
                        $response = $exception->response;
                        $metaMessage = $response?->json('error.message') ?? $exception->getMessage();

                        Notification::make()
                            ->title('No se pudo enviar el mensaje de prueba')
                            ->body((string) $metaMessage)
                            ->danger()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Error al preparar el envío')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getPendientes(): Collection
    {
        return app(AsistenciasPendientesService::class)
            ->obtener($this->getFechaReferencia());
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

        $telefono = $this->normalizarTelefonoWhatsapp(
            (string) ($facilitador['telefono_normalizado'] ?: $facilitador['telefono'] ?: '')
        );

        if ($telefono === null) {
            Notification::make()
                ->title('El facilitador no tiene un teléfono válido')
                ->danger()
                ->send();

            return;
        }

        $nombreFacilitador = $this->normalizarNombreFacilitador((string) $facilitador['nombre']);
        $periodo = Carbon::parse($item['periodo_inicio'])->format('d/m/Y') . ' al ' . Carbon::parse($item['periodo_fin'])->format('d/m/Y');
        $url = AsistenciaGruposCrecimiento::getUrl();
        $renderedBody = "Hola {$nombreFacilitador}, te recordamos cargar la asistencia del grupo {$item['grupo']} correspondiente al período {$periodo}.\n\nPuedes hacerlo aquí:\n{$url}";

        try {
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
                    'persona_id' => $personaId,
                    'grupo_id' => $grupoId,
                    'use_case' => 'recordatorio_asistencia_grupo',
                    'periodo_inicio' => (string) $item['periodo_inicio'],
                    'periodo_fin' => (string) $item['periodo_fin'],
                ],
                $renderedBody,
            );

            $this->lastReminderStatuses = [];

            Notification::make()
                ->title('Recordatorio enviado a Meta')
                ->body("Facilitador: {$nombreFacilitador}")
                ->success()
                ->send();
        } catch (RequestException $exception) {
            $metaMessage = $exception->response?->json('error.message') ?? $exception->getMessage();

            Notification::make()
                ->title('No se pudo enviar el recordatorio')
                ->body((string) $metaMessage)
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Error al preparar el recordatorio')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
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
        } catch (\Throwable) {
            return now()->startOfDay();
        }
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
            return '54' . substr($digits, 3);
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
}
