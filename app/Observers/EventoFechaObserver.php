<?php

namespace App\Observers;

use App\Models\EventoFecha;
use App\Services\AuditService;

class EventoFechaObserver
{
    public function created(EventoFecha $eventoFecha): void
    {
        AuditService::log($eventoFecha, 'created');
    }

    public function updated(EventoFecha $eventoFecha): void
    {
        if ($eventoFecha->wasChanged()) {
            AuditService::log($eventoFecha, 'updated', $eventoFecha->getChanges());
        }
    }

    public function deleted(EventoFecha $eventoFecha): void
    {
        AuditService::log($eventoFecha, 'deleted');
    }

    public function restored(EventoFecha $eventoFecha): void
    {
        AuditService::log($eventoFecha, 'restored');
    }
}
