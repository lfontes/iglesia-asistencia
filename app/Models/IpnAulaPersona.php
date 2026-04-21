<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpnAulaPersona extends Model
{
    protected $table = 'ipn_aula_persona';

    protected $fillable = [
        'ipn_aula_id',
        'persona_id',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];

    public function aula()
    {
        return $this->belongsTo(IpnAula::class, 'ipn_aula_id');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}
