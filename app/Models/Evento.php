<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $fillable = [
    'nombre',
    'descripcion',
];

public function fechas()
{
    return $this->hasMany(EventoFecha::class);
}


}
