<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolGrupo extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    public function participacionesGrupo()
    {
        return $this->hasMany(ParticipacionGrupo::class);
    }
}

