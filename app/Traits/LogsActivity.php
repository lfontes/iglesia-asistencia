<?php

namespace App\Traits;

trait LogsActivity
{
    /**
     * Boot the trait.
     */
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            activity()
                ->performedOn($model)
                ->causedBy(auth()->user())
                ->withProperties(['attributes' => $model->getAttributes()])
                ->log('created');
        });

        static::updated(function ($model) {
            activity()
                ->performedOn($model)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $model->getOriginal(),
                    'new' => $model->getChanges(),
                ])
                ->log('updated');
        });

        static::deleted(function ($model) {
            activity()
                ->performedOn($model)
                ->causedBy(auth()->user())
                ->withProperties(['attributes' => $model->getAttributes()])
                ->log('deleted');
        });
    }

    /**
     * Get the log name for the model.
     */
    public function getActivitylogOptions()
    {
        return [
            'log_name' => class_basename($this),
            'description' => class_basename($this),
        ];
    }
}
