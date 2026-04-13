<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Metagrupo extends Model
{
    protected $fillable = [
        'nombre',
        'lider_persona_id',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function lider()
    {
        return $this->belongsTo(Persona::class, 'lider_persona_id');
    }

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'grupo_metagrupo')
            ->withTimestamps()
            ->orderBy('nombre');
    }

    public function scopeWithSummaryColumns(Builder $query): Builder
    {
        return $query->addSelect([
            'grupos_count' => DB::table('grupo_metagrupo')
                ->selectRaw('COUNT(*)')
                ->whereColumn('grupo_metagrupo.metagrupo_id', 'metagrupos.id'),
            'personas_count' => DB::table('participacion_grupos as pg')
                ->join('grupo_metagrupo as gm', 'gm.grupo_id', '=', 'pg.grupo_id')
                ->selectRaw('COUNT(DISTINCT pg.persona_id)')
                ->whereColumn('gm.metagrupo_id', 'metagrupos.id')
                ->where(function ($subQuery): void {
                    $subQuery->whereNull('pg.fecha_fin')
                        ->orWhere('pg.fecha_fin', '>=', now()->toDateString());
                }),
        ]);
    }
}
