<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoInscripcion extends Model
{
    protected $table = 'evento_inscripciones';

    protected $fillable = [
        'persona_id',
        'evento_fecha_id',
        'estado',
        'observaciones',
        'datos_capturados',
    ];

    protected function casts(): array
    {
        return [
            'datos_capturados' => 'array',
        ];
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function eventoFecha()
    {
        return $this->belongsTo(EventoFecha::class);
    }
}
