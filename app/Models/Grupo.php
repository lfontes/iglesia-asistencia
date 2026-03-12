<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = [
        'nombre',
        'anio',
        'tipo_grupo_id',
        'descripcion',
        'activo',
    ];

    public function tipoGrupo()
    {
        return $this->belongsTo(TipoGrupo::class);
    }

    public function participacionesGrupo()
    {
        return $this->hasMany(ParticipacionGrupo::class);
    }

    public function asistenciasGrupo()
    {
        return $this->hasMany(AsistenciaGrupo::class);
    }
}
