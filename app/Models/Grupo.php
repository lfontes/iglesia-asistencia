<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Grupo extends Model
{
    public const FRECUENCIA_SEMANAL = 'semanal';

    public const FRECUENCIA_QUINCENAL = 'quincenal';

    public const FRECUENCIA_MENSUAL = 'mensual';

    public const FRECUENCIA_SIN_ASISTENCIA = 'sin_asistencia';

    protected $fillable = [
        'nombre',
        'anio',
        'tipo_grupo_id',
        'frecuencia_asistencia',
        'descripcion',
        'activo',
        'created_by',
        'lider_persona_id',
    ];

    public static function frecuenciasAsistencia(): array
    {
        return [
            self::FRECUENCIA_SEMANAL => 'Semanal',
            self::FRECUENCIA_QUINCENAL => 'Quincenal',
            self::FRECUENCIA_MENSUAL => 'Mensual',
            self::FRECUENCIA_SIN_ASISTENCIA => 'Sin asistencia',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $grupo): void {
            if ($grupo->esTipoMinisterio()) {
                $grupo->frecuencia_asistencia = self::FRECUENCIA_SIN_ASISTENCIA;
            }
        });
    }

    public function esTipoMinisterio(): bool
    {
        $nombreTipo = (string) ($this->tipoGrupo?->nombre
            ?? TipoGrupo::query()->whereKey($this->tipo_grupo_id)->value('nombre')
            ?? '');

        return Str::lower($nombreTipo) === 'ministerio';
    }

    public function tipoGrupo()
    {
        return $this->belongsTo(TipoGrupo::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lider()
    {
        return $this->belongsTo(Persona::class, 'lider_persona_id');
    }

    public function participacionesGrupo()
    {
        return $this->hasMany(ParticipacionGrupo::class);
    }

    public function asistenciasGrupo()
    {
        return $this->hasMany(AsistenciaGrupo::class);
    }

    public function metagrupos()
    {
        return $this->belongsToMany(Metagrupo::class, 'grupo_metagrupo')
            ->withTimestamps()
            ->orderBy('nombre');
    }

    public function isManagedBy(User $user): bool
    {
        if ($user->canManageGrupos()) {
            return true;
        }

        if (! $user->hasRole('lider')) {
            return false;
        }

        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        return $user->persona_id !== null
            && (int) $this->lider_persona_id === (int) $user->persona_id;
    }

    public function scopeManagedBy(Builder $query, User $user): Builder
    {
        if ($user->canManageGrupos()) {
            return $query;
        }

        return $query->where(function (Builder $managedQuery) use ($user): void {
            $managedQuery->where('created_by', $user->id);

            if ($user->persona_id) {
                $managedQuery->orWhere('lider_persona_id', $user->persona_id);
            }
        });
    }
}
