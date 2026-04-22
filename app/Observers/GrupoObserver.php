<?php

namespace App\Observers;

use App\Models\Grupo;
use App\Services\AuditService;

class GrupoObserver
{
    public function created(Grupo $grupo): void
    {
        AuditService::log($grupo, 'created');
    }

    public function updated(Grupo $grupo): void
    {
        if ($grupo->wasChanged()) {
            AuditService::log($grupo, 'updated', $grupo->getChanges());
        }
    }

    public function deleted(Grupo $grupo): void
    {
        AuditService::log($grupo, 'deleted');
    }

    public function restored(Grupo $grupo): void
    {
        AuditService::log($grupo, 'restored');
    }
}
