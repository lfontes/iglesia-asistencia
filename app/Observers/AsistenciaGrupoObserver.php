<?php

namespace App\Observers;

use App\Models\AsistenciaGrupo;
use App\Services\AuditService;

class AsistenciaGrupoObserver
{
    public function created(AsistenciaGrupo $asistencia): void
    {
        AuditService::log($asistencia, 'created');
    }

    public function updated(AsistenciaGrupo $asistencia): void
    {
        if ($asistencia->wasChanged()) {
            AuditService::log($asistencia, 'updated', $asistencia->getChanges());
        }
    }

    public function deleted(AsistenciaGrupo $asistencia): void
    {
        AuditService::log($asistencia, 'deleted');
    }

    public function restored(AsistenciaGrupo $asistencia): void
    {
        AuditService::log($asistencia, 'restored');
    }
}
