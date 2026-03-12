<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Persona extends Model
{
    protected $fillable = [
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'telefono',
        'telefono_normalizado',
    ];

    public function setTelefonoAttribute(mixed $value): void
    {
        $telefono = is_string($value) ? trim($value) : null;
        $telefono = $telefono !== '' ? $telefono : null;

        $this->attributes['telefono'] = $telefono;
        $this->attributes['telefono_normalizado'] = $this->normalizePhone($telefono);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function participacionesGrupo()
    {
        return $this->hasMany(ParticipacionGrupo::class);
    }

    public function asistenciasGrupo()
    {
        return $this->hasMany(AsistenciaGrupo::class);
    }

    protected function normalizePhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    public function scopeBuscarPorNombreApellido(Builder $query, string $search): Builder
    {
        $normalized = (string) Str::of($search)
            ->lower()
            ->ascii()
            ->squish();

        $normalizeSql = static fn (string $column): string => "translate(lower({$column}), 'áéíóúäëïöüàèìòùâêîôûãõñç', 'aeiouaeiouaeiouaeiouaonc')";

        return $query->where(function (Builder $q) use ($normalized, $normalizeSql): void {
            $q->whereRaw($normalizeSql('nombre').' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql('apellido').' LIKE ?', ["%{$normalized}%"]);
        });
    }
}
