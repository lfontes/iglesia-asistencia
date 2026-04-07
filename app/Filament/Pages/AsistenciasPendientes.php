<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
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
}
