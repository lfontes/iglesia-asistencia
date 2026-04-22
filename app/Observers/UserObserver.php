<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;

class UserObserver
{
    public function created(User $user): void
    {
        AuditService::log($user, 'created');
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged()) {
            AuditService::log($user, 'updated', $user->getChanges());
        }
    }

    public function deleted(User $user): void
    {
        AuditService::log($user, 'deleted');
    }

    public function restored(User $user): void
    {
        AuditService::log($user, 'restored');
    }
}
