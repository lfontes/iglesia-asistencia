<?php

namespace App\Observers;

use App\Models\Evento;
use App\Services\AuditService;

class EventoObserver
{
    public function created(Evento $evento): void
    {
        AuditService::log($evento, 'created');
    }

    public function updated(Evento $evento): void
    {
        if ($evento->wasChanged()) {
            AuditService::log($evento, 'updated', $evento->getChanges());
        }
    }

    public function deleted(Evento $evento): void
    {
        AuditService::log($evento, 'deleted');
    }

    public function restored(Evento $evento): void
    {
        AuditService::log($evento, 'restored');
    }
}
