<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoFecha extends Model
{
    protected $fillable = [
    'evento_id',
    'fecha',
    'observaciones',
];

public function evento()
{
    return $this->belongsTo(Evento::class);
}

public function asistencias()
{
    return $this->hasMany(Asistencia::class);
}

}
