<?php

namespace App\Services;

use App\Models\Persona;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersonaMatchingService
{
    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, Persona>
     */
    public function findCandidates(array $input): Collection
    {
        $normalizedPhone = $this->normalizePhone($input['telefono'] ?? null);
        $normalizedNombre = $this->normalizeText($input['nombre'] ?? null);
        $normalizedApellido = $this->normalizeText($input['apellido'] ?? null);
        $fechaNacimiento = $input['fecha_nacimiento'] ?? null;

        if ($normalizedPhone) {
            $byPhone = Persona::query()
                ->where('telefono_normalizado', $normalizedPhone)
                ->get();

            if ($byPhone->isNotEmpty()) {
                return $this->rankCandidates(
                    $byPhone,
                    $normalizedNombre,
                    $normalizedApellido,
                    $normalizedPhone,
                    $fechaNacimiento,
                );
            }
        }

        $candidates = Persona::query()
            ->when($fechaNacimiento, fn ($query) => $query->where('fecha_nacimiento', $fechaNacimiento))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Persona $persona): bool => $this->looksLikeMatch($persona, $normalizedNombre, $normalizedApellido))
            ->values();

        return $this->rankCandidates(
            $candidates,
            $normalizedNombre,
            $normalizedApellido,
            $normalizedPhone,
            $fechaNacimiento,
        );
    }

    protected function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    protected function normalizeText(mixed $value): string
    {
        return (string) Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9 ]+/', ' ')
            ->squish();
    }

    protected function looksLikeMatch(Persona $persona, string $normalizedNombre, string $normalizedApellido): bool
    {
        $personaNombre = $this->normalizeText($persona->nombre);
        $personaApellido = $this->normalizeText($persona->apellido);

        if ($normalizedApellido === '' || $normalizedNombre === '') {
            return false;
        }

        return Str::contains($personaApellido, $normalizedApellido)
            && (
                Str::contains($personaNombre, $normalizedNombre)
                || Str::contains($normalizedNombre, $personaNombre)
                || collect(explode(' ', $normalizedNombre))
                    ->filter()
                    ->contains(fn (string $token): bool => Str::contains($personaNombre, $token))
            );
    }

    /**
     * @param  Collection<int, Persona>  $candidates
     * @return Collection<int, Persona>
     */
    protected function rankCandidates(
        Collection $candidates,
        string $normalizedNombre,
        string $normalizedApellido,
        ?string $normalizedPhone,
        mixed $fechaNacimiento,
    ): Collection {
        return $candidates
            ->map(function (Persona $persona) use ($normalizedNombre, $normalizedApellido, $normalizedPhone, $fechaNacimiento): array {
                $score = 0;
                $personaNombre = $this->normalizeText($persona->nombre);
                $personaApellido = $this->normalizeText($persona->apellido);

                if ($normalizedPhone && $persona->telefono_normalizado === $normalizedPhone) {
                    $score += 100;
                }

                if ($fechaNacimiento && $persona->fecha_nacimiento === $fechaNacimiento) {
                    $score += 50;
                }

                if ($normalizedApellido !== '' && $personaApellido === $normalizedApellido) {
                    $score += 30;
                } elseif ($normalizedApellido !== '' && Str::contains($personaApellido, $normalizedApellido)) {
                    $score += 15;
                }

                if ($normalizedNombre !== '') {
                    if ($personaNombre === $normalizedNombre) {
                        $score += 30;
                    } elseif (Str::contains($personaNombre, $normalizedNombre) || Str::contains($normalizedNombre, $personaNombre)) {
                        $score += 20;
                    }

                    foreach (collect(explode(' ', $normalizedNombre))->filter() as $token) {
                        if (Str::contains($personaNombre, $token)) {
                            $score += 5;
                        }
                    }
                }

                return [
                    'persona' => $persona,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->pluck('persona')
            ->unique('id')
            ->take(3)
            ->values();
    }
}
