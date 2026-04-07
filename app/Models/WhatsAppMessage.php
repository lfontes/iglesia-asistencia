<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'provider_message_id',
        'to_phone',
        'recipient_wa_id',
        'body',
        'direction',
        'status',
        'error_message',
        'response_payload',
        'webhook_payload',
        'accepted_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'webhook_payload' => 'array',
            'accepted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
