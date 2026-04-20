<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EventoFecha extends Model
{
    protected $fillable = [
        'evento_id',
        'fecha',
        'observaciones',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function inscripciones()
    {
        return $this->hasMany(EventoInscripcion::class);
    }

    public function publicInscriptionUrl(): string
    {
        $path = route('eventos.inscripcion.create', $this, false);

        return rtrim((string) config('app.public_url'), '/') . $path;
    }

    /**
     * @return Collection<int, EventoInscripcion>
     */
    public function inscriptosConTelefono(): Collection
    {
        return $this->inscripciones()
            ->with('persona:id,nombre,apellido,telefono,telefono_normalizado')
            ->where('estado', 'inscripto')
            ->get()
            ->filter(fn (EventoInscripcion $inscripcion): bool => filled($inscripcion->persona?->telefono_normalizado ?: $inscripcion->persona?->telefono))
            ->values();
    }
}
