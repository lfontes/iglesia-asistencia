<?php

namespace App\Observers;

use App\Models\Persona;
use App\Services\AuditService;

class PersonaObserver
{
    public function created(Persona $persona): void
    {
        AuditService::log($persona, 'created');
    }

    public function updated(Persona $persona): void
    {
        if ($persona->wasChanged()) {
            AuditService::log($persona, 'updated', $persona->getChanges());
        }
    }

    public function deleted(Persona $persona): void
    {
        AuditService::log($persona, 'deleted');
    }

    public function restored(Persona $persona): void
    {
        AuditService::log($persona, 'restored');
    }
}
