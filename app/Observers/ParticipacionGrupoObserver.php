<?php

namespace App\Observers;

use App\Models\ParticipacionGrupo;
use App\Services\AuditService;

class ParticipacionGrupoObserver
{
    public function created(ParticipacionGrupo $participacion): void
    {
        AuditService::log($participacion, 'created');
    }

    public function updated(ParticipacionGrupo $participacion): void
    {
        if ($participacion->wasChanged()) {
            AuditService::log($participacion, 'updated', $participacion->getChanges());
        }
    }

    public function deleted(ParticipacionGrupo $participacion): void
    {
        AuditService::log($participacion, 'deleted');
    }

    public function restored(ParticipacionGrupo $participacion): void
    {
        AuditService::log($participacion, 'restored');
    }
}
