<?php

namespace App\Observers;

use App\Models\WhatsAppMessage;
use App\Services\AuditService;

class WhatsAppMessageObserver
{
    public function created(WhatsAppMessage $message): void
    {
        AuditService::log($message, 'created');
    }

    public function updated(WhatsAppMessage $message): void
    {
        if ($message->wasChanged()) {
            AuditService::log($message, 'updated', $message->getChanges());
        }
    }

    public function deleted(WhatsAppMessage $message): void
    {
        AuditService::log($message, 'deleted');
    }

    public function restored(WhatsAppMessage $message): void
    {
        AuditService::log($message, 'restored');
    }
}
