<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    public const FRECUENCIA_SEMANAL = 'semanal';

    public const FRECUENCIA_QUINCENAL = 'quincenal';

    public const FRECUENCIA_MENSUAL = 'mensual';

    protected $fillable = [
        'nombre',
        'anio',
        'tipo_grupo_id',
        'frecuencia_asistencia',
        'descripcion',
        'activo',
    ];

    public static function frecuenciasAsistencia(): array
    {
        return [
            self::FRECUENCIA_SEMANAL => 'Semanal',
            self::FRECUENCIA_QUINCENAL => 'Quincenal',
            self::FRECUENCIA_MENSUAL => 'Mensual',
        ];
    }

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

    public function metagrupos()
    {
        return $this->belongsToMany(Metagrupo::class, 'grupo_metagrupo')
            ->withTimestamps()
            ->orderBy('nombre');
    }
}
