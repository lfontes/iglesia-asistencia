<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsistenciaGrupo extends Model
{
    protected $fillable = [
        'grupo_id',
        'persona_id',
        'fecha',
        'presente',
        'observaciones',
        'created_by',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
