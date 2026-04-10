<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppMessage;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class WhatsAppConversaciones extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Conversaciones';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Conversaciones WhatsApp';

    protected static string $view = 'filament.pages.whatsapp-conversaciones';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getConversations(): Collection
    {
        return WhatsAppMessage::query()
            ->with(['persona:id,nombre,apellido', 'grupo:id,nombre'])
            ->whereNotNull('conversation_key')
            ->latest('created_at')
            ->get()
            ->groupBy('conversation_key')
            ->map(function (Collection $messages, string $conversationKey): array {
                /** @var WhatsAppMessage $latest */
                $latest = $messages->first();

                $personaMessage = $messages->first(fn (WhatsAppMessage $message) => $message->persona);
                $grupoMessage = $messages->first(fn (WhatsAppMessage $message) => $message->grupo);
                $lastInboundAt = $messages
                    ->filter(fn (WhatsAppMessage $message): bool => $message->isInbound())
                    ->max('created_at');

                return [
                    'conversation_key' => $conversationKey,
                    'persona' => $personaMessage?->persona,
                    'grupo' => $grupoMessage?->grupo,
                    'telefono' => $latest->from_phone ?: $latest->to_phone ?: $conversationKey,
                    'ultimo_texto' => $latest->body,
                    'ultimo_momento' => $latest->created_at,
                    'no_leidos' => $messages->filter(fn (WhatsAppMessage $message): bool => $message->isUnreadInApp())->count(),
                    'ventana_abierta' => $lastInboundAt !== null && now()->diffInHours($lastInboundAt) < 24,
                ];
            })
            ->sortByDesc(fn (array $conversation) => $conversation['ultimo_momento'])
            ->values();
    }
}
