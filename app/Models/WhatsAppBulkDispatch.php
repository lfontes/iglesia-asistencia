<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppBulkDispatch extends Model
{
    protected $table = 'whatsapp_bulk_dispatches';

    protected $fillable = [
        'use_case',
        'fecha_referencia',
        'period_hash',
        'period_summary',
        'user_id',
        'sent_count',
        'skipped_count',
        'failed_count',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_referencia' => 'date',
            'meta' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
