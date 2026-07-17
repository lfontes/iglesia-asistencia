<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Grupo extends Model
{
    public const SEGMENTO_NINOS = 'ninos';

    public const SEGMENTO_ADOLESCENTES = 'adolescentes';

    public const SEGMENTO_JOVENES = 'jovenes';

    public const SEGMENTO_ADULTOS = 'adultos';

    public const FRECUENCIA_SEMANAL = 'semanal';

    public const FRECUENCIA_QUINCENAL = 'quincenal';

    public const FRECUENCIA_MENSUAL = 'mensual';

    public const FRECUENCIA_SIN_ASISTENCIA = 'sin_asistencia';

    protected $fillable = [
        'nombre',
        'anio',
        'tipo_grupo_id',
        'segmento_etario',
        'edad_min',
        'edad_max',
        'frecuencia_asistencia',
        'descripcion',
        'activo',
        'created_by',
        'lider_persona_id',
    ];

    protected $casts = [
        'edad_min' => 'integer',
        'edad_max' => 'integer',
        'activo' => 'boolean',
    ];

    public static function segmentosEtarios(): array
    {
        return [
            self::SEGMENTO_NINOS => 'Ninos',
            self::SEGMENTO_ADOLESCENTES => 'Adolescentes',
            self::SEGMENTO_JOVENES => 'Jovenes',
            self::SEGMENTO_ADULTOS => 'Adultos',
        ];
    }

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

        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $user->persona_id !== null
            && $this->metagrupos()->where('lider_persona_id', $user->persona_id)->exists();
    }

    public function isOwnedBy(User $user): bool
    {
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
                $managedQuery->orWhere('lider_persona_id', $user->persona_id)
                    ->orWhereHas('metagrupos', function (Builder $metagrupoQuery) use ($user): void {
                        $metagrupoQuery->where('lider_persona_id', $user->persona_id);
                    });
            }
        });
    }

    public function getSegmentoEtarioLabelAttribute(): ?string
    {
        return static::segmentosEtarios()[$this->segmento_etario] ?? null;
    }

    public function getRangoEdadLabelAttribute(): ?string
    {
        if ($this->edad_min !== null && $this->edad_max !== null) {
            return "{$this->edad_min} a {$this->edad_max} anos";
        }

        if ($this->edad_min !== null) {
            return "Desde {$this->edad_min} anos";
        }

        if ($this->edad_max !== null) {
            return "Hasta {$this->edad_max} anos";
        }

        return null;
    }
}
