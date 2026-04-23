<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Persona extends Model
{
    public const DEPARTAMENTOS_MENDOZA = [
        'Capital' => 'Capital',
        'General Alvear' => 'General Alvear',
        'Godoy Cruz' => 'Godoy Cruz',
        'Guaymallén' => 'Guaymallén',
        'Junín' => 'Junín',
        'La Paz' => 'La Paz',
        'Las Heras' => 'Las Heras',
        'Lavalle' => 'Lavalle',
        'Luján de Cuyo' => 'Luján de Cuyo',
        'Maipú' => 'Maipú',
        'Malargüe' => 'Malargüe',
        'Rivadavia' => 'Rivadavia',
        'San Carlos' => 'San Carlos',
        'San Martín' => 'San Martín',
        'San Rafael' => 'San Rafael',
        'Santa Rosa' => 'Santa Rosa',
        'Tunuyán' => 'Tunuyán',
        'Tupungato' => 'Tupungato',
    ];

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
        'departamento',
        'telefono_normalizado',
        'es_menor',
        'responsable_persona_id',
        'responsable_nombre',
        'responsable_telefono',
        'responsable_telefono_normalizado',
        'observaciones_ipn',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'es_menor' => 'boolean',
    ];

    public function setTelefonoAttribute(mixed $value): void
    {
        $telefono = is_string($value) ? trim($value) : null;
        $telefono = $telefono !== '' ? $telefono : null;

        $this->attributes['telefono'] = $telefono;
        $this->attributes['telefono_normalizado'] = $this->normalizePhone($telefono);
    }

    public function setResponsableTelefonoAttribute(mixed $value): void
    {
        $telefono = is_string($value) ? trim($value) : null;
        $telefono = $telefono !== '' ? $telefono : null;

        $this->attributes['responsable_telefono'] = $telefono;
        $this->attributes['responsable_telefono_normalizado'] = $this->normalizePhone($telefono);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function eventoInscripciones()
    {
        return $this->hasMany(EventoInscripcion::class);
    }

    public function participacionesGrupo()
    {
        return $this->hasMany(ParticipacionGrupo::class);
    }

    public function asistenciasGrupo()
    {
        return $this->hasMany(AsistenciaGrupo::class);
    }

    public function ipnParticipaciones()
    {
        return $this->hasMany(IpnAulaPersona::class);
    }

    public function ipnAulas()
    {
        return $this->belongsToMany(IpnAula::class, 'ipn_aula_persona', 'persona_id', 'ipn_aula_id')
            ->withPivot(['fecha_inicio', 'fecha_fin', 'activo', 'observaciones'])
            ->withTimestamps();
    }

    public function ipnAsistencias()
    {
        return $this->hasMany(IpnAsistencia::class);
    }

    public function ipnAulasServidor()
    {
        return $this->hasMany(IpnAulaServidor::class);
    }

    public function responsablePersona()
    {
        return $this->belongsTo(self::class, 'responsable_persona_id');
    }

    public function menoresResponsables()
    {
        return $this->hasMany(self::class, 'responsable_persona_id');
    }

    public function metagruposLiderados()
    {
        return $this->hasMany(Metagrupo::class, 'lider_persona_id');
    }

    public function participacionesGrupoLideradas(): HasMany
    {
        return $this->hasMany(ParticipacionGrupo::class)
            ->where(function (Builder $query): void {
                $query->whereHas('rolGrupo', function (Builder $roleQuery): void {
                    $roleQuery->whereRaw('LOWER(nombre) LIKE ?', ['%líder%'])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ['%lider%']);
                });
            });
    }

    public function gruposMinisterialesLiderados(): Builder
    {
        return Grupo::query()
            ->whereHas('participacionesGrupo', function (Builder $query): void {
                $query->where('persona_id', $this->id)
                    ->where(function (Builder $leaderQuery): void {
                        $leaderQuery->whereHas('rolGrupo', function (Builder $roleQuery): void {
                            $roleQuery->whereRaw('LOWER(nombre) LIKE ?', ['%líder%'])
                                ->orWhereRaw('LOWER(nombre) LIKE ?', ['%lider%']);
                        });
                    })
                    ->where(function (Builder $activeQuery): void {
                        $activeQuery->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                    });
            })
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('tipoGrupo', function (Builder $typeQuery): void {
                    $typeQuery->whereRaw('LOWER(nombre) = ?', ['crecimiento']);
                })->orWhereNull('tipo_grupo_id');
            })
            ->with('tipoGrupo:id,nombre')
            ->orderBy('nombre');
    }

    public function lideraMetagrupo(Metagrupo $metagrupo): bool
    {
        return (int) $metagrupo->lider_persona_id === (int) $this->id;
    }

    public function lideraGrupoMinisterial(int $grupoId): bool
    {
        return $this->gruposMinisterialesLiderados()
            ->whereKey($grupoId)
            ->exists();
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function getEdadAttribute(): ?int
    {
        if (! $this->fecha_nacimiento) {
            return null;
        }

        return Carbon::parse($this->fecha_nacimiento)->age;
    }

    public function responsableIpnLabel(): ?string
    {
        if ($this->responsablePersona) {
            return trim("{$this->responsablePersona->apellido} {$this->responsablePersona->nombre}");
        }

        return $this->responsable_nombre;
    }

    public function responsableIpnTelefono(): ?string
    {
        return $this->responsablePersona?->telefono ?: $this->responsable_telefono;
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

        $normalizeSql = fn (string $expression): string => $this->normalizedSearchExpression($expression);

        return $query->where(function (Builder $q) use ($normalized, $normalizeSql): void {
            $q->whereRaw($normalizeSql('nombre').' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql('apellido').' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql("concat_ws(' ', nombre, apellido)").' LIKE ?', ["%{$normalized}%"])
                ->orWhereRaw($normalizeSql("concat_ws(' ', apellido, nombre)").' LIKE ?', ["%{$normalized}%"]);
        });
    }

    protected function normalizedSearchExpression(string $expression): string
    {
        $lowered = "lower({$expression})";

        $normalized = $lowered;

        foreach (self::SEARCH_ACCENT_MAP as $from => $to) {
            $normalized = "replace({$normalized}, '{$from}', '{$to}')";
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function departamentosMendoza(): array
    {
        return self::DEPARTAMENTOS_MENDOZA;
    }
}
