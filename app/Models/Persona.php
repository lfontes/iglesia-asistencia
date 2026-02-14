<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    //
    protected $fillable = [
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'telefono',
    ];

    public function clases()
    {
        return $this->belongsToMany(Clase::class);
    }
}
