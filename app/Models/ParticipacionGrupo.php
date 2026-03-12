<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipacionGrupo extends Model
{
    protected $fillable = [
        'persona_id',
        'grupo_id',
        'rol_grupo_id',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'observaciones',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function rolGrupo()
    {
        return $this->belongsTo(RolGrupo::class);
    }
}

