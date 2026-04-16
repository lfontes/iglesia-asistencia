<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'provider_message_id',
        'reply_to_provider_message_id',
        'to_phone',
        'from_phone',
        'recipient_wa_id',
        'conversation_key',
        'persona_id',
        'grupo_id',
        'evento_fecha_id',
        'body',
        'direction',
        'message_type',
        'use_case',
        'periodo_inicio',
        'periodo_fin',
        'status',
        'error_message',
        'response_payload',
        'webhook_payload',
        'accepted_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'read_in_app_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'webhook_payload' => 'array',
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
            'accepted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'read_in_app_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function eventoFecha()
    {
        return $this->belongsTo(EventoFecha::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isUnreadInApp(): bool
    {
        return $this->isInbound() && $this->read_in_app_at === null;
    }
}
