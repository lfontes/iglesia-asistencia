<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppMessage;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class WhatsAppMensajes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static ?string $navigationGroup = 'Integraciones';

    protected static ?string $navigationLabel = 'Mensajes WhatsApp';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Mensajes WhatsApp';

    protected static string $view = 'filament.pages.whatsapp-mensajes';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return Collection<int, WhatsAppMessage>
     */
    public function getMessages(): Collection
    {
        return WhatsAppMessage::query()
            ->with(['persona:id,nombre,apellido', 'grupo:id,nombre'])
            ->latest('id')
            ->limit(100)
            ->get();
    }
}
