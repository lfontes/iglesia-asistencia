<?php

namespace App\Observers;

use App\Models\Asistencia;
use App\Services\AuditService;

class AsistenciaObserver
{
    public function created(Asistencia $asistencia): void
    {
        AuditService::log($asistencia, 'created');
    }

    public function updated(Asistencia $asistencia): void
    {
        if ($asistencia->wasChanged()) {
            AuditService::log($asistencia, 'updated', $asistencia->getChanges());
        }
    }

    public function deleted(Asistencia $asistencia): void
    {
        AuditService::log($asistencia, 'deleted');
    }

    public function restored(Asistencia $asistencia): void
    {
        AuditService::log($asistencia, 'restored');
    }
}
