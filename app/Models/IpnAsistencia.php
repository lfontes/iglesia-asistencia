<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpnAsistencia extends Model
{
    protected $table = 'ipn_asistencias';

    protected $fillable = [
        'ipn_aula_id',
        'persona_id',
        'fecha',
        'presente',
        'observaciones',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'presente' => 'boolean',
    ];

    public function aula()
    {
        return $this->belongsTo(IpnAula::class, 'ipn_aula_id');
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
