<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class WhatsAppConversacion extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Conversación WhatsApp';

    protected static string $view = 'filament.pages.whatsapp-conversacion';

    public ?string $key = null;

    public ?string $message = null;

    public function mount(): void
    {
        $this->key = (string) request()->query('key', '');

        $this->form->fill([
            'message' => null,
        ]);

        $this->markConversationAsRead();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('message')
                ->label('Responder')
                ->rows(4)
                ->placeholder('Escribe una respuesta...')
                ->required(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver a conversaciones')
                ->url(WhatsAppConversaciones::getUrl())
                ->color('gray'),
            Action::make('enviar')
                ->label('Enviar respuesta')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->disabled(fn (): bool => ! $this->isWindowOpen())
                ->action(fn () => $this->sendReply()),
        ];
    }

    /**
     * @return Collection<int, WhatsAppMessage>
     */
    public function getConversationMessages(): Collection
    {
        if (blank($this->key)) {
            return collect();
        }

        return WhatsAppMessage::query()
            ->with(['persona:id,nombre,apellido', 'grupo:id,nombre'])
            ->where('conversation_key', $this->key)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array{
     *     persona: \App\Models\Persona|null,
     *     grupo: \App\Models\Grupo|null,
     *     telefono: string|null,
     *     ultimo_momento: \Illuminate\Support\Carbon|null,
     *     ventana_abierta: bool
     * }|null
     */
    public function getConversationSummary(): ?array
    {
        $messages = $this->getConversationMessages();

        if ($messages->isEmpty()) {
            return null;
        }

        $latest = $messages->last();
        $personaMessage = $messages->first(fn (WhatsAppMessage $message) => $message->persona);
        $grupoMessage = $messages->first(fn (WhatsAppMessage $message) => $message->grupo);
        $lastInboundAt = $messages
            ->filter(fn (WhatsAppMessage $message): bool => $message->isInbound())
            ->max('created_at');
        $phone = $messages
            ->map(fn (WhatsAppMessage $message): ?string => $message->from_phone ?: $message->to_phone)
            ->filter()
            ->last();

        return [
            'persona' => $personaMessage?->persona,
            'grupo' => $grupoMessage?->grupo,
            'telefono' => $phone ?: $this->key,
            'ultimo_momento' => $latest->created_at,
            'ventana_abierta' => $lastInboundAt !== null && now()->diffInHours($lastInboundAt) < 24,
        ];
    }

    public function isWindowOpen(): bool
    {
        return (bool) ($this->getConversationSummary()['ventana_abierta'] ?? false);
    }

    protected function sendReply(): void
    {
        $summary = $this->getConversationSummary();

        if (! $summary || blank($summary['telefono'])) {
            Notification::make()
                ->title('No se encontró el destinatario')
                ->danger()
                ->send();

            return;
        }

        if (! $summary['ventana_abierta']) {
            Notification::make()
                ->title('La ventana de 24 horas está cerrada')
                ->body('Solo puedes responder con texto libre cuando la persona escribió en las últimas 24 horas.')
                ->warning()
                ->send();

            return;
        }

        $body = trim((string) ($this->form->getState()['message'] ?? ''));

        if ($body === '') {
            Notification::make()
                ->title('Escribe un mensaje antes de enviar')
                ->warning()
                ->send();

            return;
        }

        try {
            app(WhatsAppService::class)->sendText((string) $summary['telefono'], $body);

            $this->form->fill(['message' => null]);

            Notification::make()
                ->title('Respuesta enviada')
                ->success()
                ->send();
        } catch (RequestException $exception) {
            Notification::make()
                ->title('No se pudo enviar la respuesta')
                ->body((string) ($exception->response?->json('error.message') ?? $exception->getMessage()))
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Error al enviar la respuesta')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function markConversationAsRead(): void
    {
        if (blank($this->key)) {
            return;
        }

        WhatsAppMessage::query()
            ->where('conversation_key', $this->key)
            ->where('direction', 'inbound')
            ->whereNull('read_in_app_at')
            ->update(['read_in_app_at' => now()]);
    }
}
