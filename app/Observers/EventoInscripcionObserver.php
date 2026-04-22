<?php

namespace App\Observers;

use App\Models\EventoInscripcion;
use App\Services\AuditService;

class EventoInscripcionObserver
{
    public function created(EventoInscripcion $inscripcion): void
    {
        AuditService::log($inscripcion, 'created');
    }

    public function updated(EventoInscripcion $inscripcion): void
    {
        if ($inscripcion->wasChanged()) {
            AuditService::log($inscripcion, 'updated', $inscripcion->getChanges());
        }
    }

    public function deleted(EventoInscripcion $inscripcion): void
    {
        AuditService::log($inscripcion, 'deleted');
    }

    public function restored(EventoInscripcion $inscripcion): void
    {
        AuditService::log($inscripcion, 'restored');
    }
}
