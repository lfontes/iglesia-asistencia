<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class AuditService
{
    /**
     * Log activity for a model
     */
    public static function log(Model $model, string $event, array $changes = []): void
    {
        if (!auth()->check()) {
            return;
        }

        $properties = [];

        switch ($event) {
            case 'created':
                $properties['attributes'] = $model->getAttributes();
                $description = 'Registro creado';
                break;

            case 'updated':
                $properties['old'] = array_intersect_key(
                    $model->getOriginal(),
                    array_flip(array_keys($changes))
                );
                $properties['new'] = $changes;
                $description = 'Registro actualizado';
                break;

            case 'deleted':
                $properties['attributes'] = $model->getAttributes();
                $description = 'Registro eliminado';
                break;

            case 'restored':
                $properties['attributes'] = $model->getAttributes();
                $description = 'Registro restaurado';
                break;

            default:
                $description = 'Acción: ' . $event;
        }

        activity()
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->withProperties($properties)
            ->event($event)
            ->log($event);
    }

    /**
     * Get activity logs for a model
     */
    public static function getModelLogs(Model $model, int $limit = 50)
    {
        return $model->activities()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get user activity logs
     */
    public static function getUserLogs($limit = 100)
    {
        if (!auth()->check()) {
            return collect();
        }

        return Activity::query()
            ->where('causer_id', auth()->id())
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activity
     */
    public static function getRecentActivity(int $limit = 50)
    {
        return Activity::query()
            ->with('subject', 'causer')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
