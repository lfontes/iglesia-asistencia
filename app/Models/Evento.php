<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $fillable = [
        'nombre',
        'tipo_evento_id',
        'descripcion',
    ];

    public function fechas()
    {
        return $this->hasMany(EventoFecha::class);
    }

    public function tipoEvento()
    {
        return $this->belongsTo(TipoEvento::class);
    }
}
