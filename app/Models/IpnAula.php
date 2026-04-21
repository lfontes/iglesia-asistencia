<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IpnAula extends Model
{
    protected $table = 'ipn_aulas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'edad_desde',
        'edad_hasta',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'edad_desde' => 'integer',
        'edad_hasta' => 'integer',
    ];

    public function participaciones()
    {
        return $this->hasMany(IpnAulaPersona::class, 'ipn_aula_id');
    }

    public function participacionesActivas()
    {
        return $this->participaciones()
            ->where('activo', true)
            ->where(function (Builder $query): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', now()->toDateString());
            });
    }

    public function personas()
    {
        return $this->belongsToMany(Persona::class, 'ipn_aula_persona', 'ipn_aula_id', 'persona_id')
            ->withPivot(['fecha_inicio', 'fecha_fin', 'activo', 'observaciones'])
            ->withTimestamps();
    }

    public function asistencias()
    {
        return $this->hasMany(IpnAsistencia::class, 'ipn_aula_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function rangoEdadLabel(): string
    {
        if ($this->edad_desde !== null && $this->edad_hasta !== null) {
            return "{$this->edad_desde} a {$this->edad_hasta} años";
        }

        if ($this->edad_desde !== null) {
            return "Desde {$this->edad_desde} años";
        }

        if ($this->edad_hasta !== null) {
            return "Hasta {$this->edad_hasta} años";
        }

        return 'Sin rango';
    }
}
