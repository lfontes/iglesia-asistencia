<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ParticipacionGrupo extends Model
{
    protected $fillable = [
        'persona_id',
        'grupo_id',
        'rol_grupo_id',
        'recibe_recordatorios',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'observaciones',
    ];

    protected $casts = [
        'recibe_recordatorios' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $participacion): void {
            if (! $participacion->esFacilitador()) {
                $participacion->recibe_recordatorios = false;
            }
        });

        static::saved(function (self $participacion): void {
            if (! $participacion->recibe_recordatorios || ! $participacion->esFacilitador()) {
                return;
            }

            static::query()
                ->where('grupo_id', $participacion->grupo_id)
                ->whereKeyNot($participacion->getKey())
                ->whereHas('rolGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%facilit%']))
                ->update(['recibe_recordatorios' => false]);
        });
    }

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

    public function esFacilitador(): bool
    {
        $nombreRol = (string) ($this->rolGrupo?->nombre
            ?? RolGrupo::query()->whereKey($this->rol_grupo_id)->value('nombre')
            ?? '');

        return Str::contains(Str::lower($nombreRol), 'facilit');
    }
}
