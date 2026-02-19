<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $fillable = [
    'persona_id',
    'evento_fecha_id',
    'presente',
    'observaciones',
];

public function persona()
{
    return $this->belongsTo(Persona::class);
}

public function eventoFecha()
{
    return $this->belongsTo(EventoFecha::class);
}
}
