<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeVigenteEnFecha(Builder $query, string $fecha): Builder
    {
        return $query
            ->where('activo', true)
            ->where(function (Builder $subQuery) use ($fecha): void {
                $subQuery->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fecha);
            })
            ->where(function (Builder $subQuery) use ($fecha): void {
                $subQuery->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fecha);
            });
    }

    public function scopeVigenteEnAnio(Builder $query, int $anio): Builder
    {
        return $query
            ->where(function (Builder $subQuery) use ($anio): void {
                $subQuery->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', "{$anio}-12-31");
            })
            ->where(function (Builder $subQuery) use ($anio): void {
                $subQuery->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', "{$anio}-01-01");
            });
    }
}
