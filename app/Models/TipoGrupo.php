<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoGrupo extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
}

