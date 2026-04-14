<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Persona extends Model
{
    protected const SEARCH_ACCENT_MAP = [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ä' => 'a',
        'ë' => 'e',
        'ï' => 'i',
        'ö' => 'o',
        'ü' => 'u',
        'à' => 'a',
        'è' => 'e',
        'ì' => 'i',
        'ò' => 'o',
        'ù' => 'u',
        'â' => 'a',
        'ê' => 'e',
        'î' => 'i',
        'ô' => 'o',
        'û' => 'u',
        'ã' => 'a',
        'õ' => 'o',
        'ñ' => 'n',
        'ç' => 'c',
    ];

    protected $fillable = [
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'telefono',
        'email',
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

    public function metagruposLiderados()
    {
        return $this->hasMany(Metagrupo::class, 'lider_persona_id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
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

        if ($normalized === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $normalizeSql = fn (string $expression): string => $this->normalizedSearchExpression($expression, $driver);

        return $query->where(function (Builder $q) use ($normalized, $normalizeSql): void {
            $q->whereRaw($normalizeSql('nombre').' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql('apellido').' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql("concat_ws(' ', nombre, apellido)").' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql("concat_ws(' ', apellido, nombre)").' LIKE ?', ["%{$normalized}%"]);
        });
    }

    protected function normalizedSearchExpression(string $expression, string $driver): string
    {
        $lowered = "lower({$expression})";

        if ($driver === 'pgsql') {
            $from = implode('', array_keys(self::SEARCH_ACCENT_MAP));
            $to = implode('', array_values(self::SEARCH_ACCENT_MAP));

            return "translate({$lowered}, '{$from}', '{$to}')";
        }

        $normalized = $lowered;

        foreach (self::SEARCH_ACCENT_MAP as $from => $to) {
            $normalized = "replace({$normalized}, '{$from}', '{$to}')";
        }

        return $normalized;
    }
}
