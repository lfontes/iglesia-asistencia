<?php

namespace App\Http\Controllers;

use App\Models\EventoFecha;
use App\Models\EventoInscripcion;
use App\Models\Persona;
use App\Services\PersonaMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventoInscripcionController extends Controller
{
    public function create(EventoFecha $eventoFecha): View
    {
        return view('eventos.inscripcion', [
            'eventoFecha' => $eventoFecha->load('evento'),
            'candidates' => collect(),
            'departamentos' => Persona::departamentosMendoza(),
            'input' => old(),
        ]);
    }

    public function store(Request $request, EventoFecha $eventoFecha, PersonaMatchingService $matchingService): View|RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:100', Rule::in(array_keys(Persona::departamentosMendoza()))],
            'modo' => ['nullable', 'in:confirmar_existente,crear_nueva'],
            'persona_existente_id' => ['nullable', 'integer'],
        ]);

        if (($validated['modo'] ?? null) === null) {
            $candidates = $matchingService->findCandidates($validated);

            if ($candidates->isNotEmpty()) {
                return view('eventos.inscripcion', [
                    'eventoFecha' => $eventoFecha->load('evento'),
                    'candidates' => $candidates,
                    'departamentos' => Persona::departamentosMendoza(),
                    'input' => $validated,
                ]);
            }
        }

        $persona = $this->resolvePersona($validated);
        [, $created] = $this->registrarInscripcion($persona, $eventoFecha, $validated);

        $message = $created
            ? 'Tu inscripción quedó registrada correctamente.'
            : 'Ya estabas inscripto para esta fecha. Actualizamos tus datos.';

        return redirect()
            ->route('eventos.inscripcion.create', $eventoFecha)
            ->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: EventoInscripcion, 1: bool}
     */
    protected function registrarInscripcion(Persona $persona, EventoFecha $eventoFecha, array $validated): array
    {
        $existing = EventoInscripcion::query()
            ->where('persona_id', $persona->id)
            ->where('evento_fecha_id', $eventoFecha->id)
            ->first();

        $inscripcion = EventoInscripcion::query()->updateOrCreate(
            [
                'persona_id' => $persona->id,
                'evento_fecha_id' => $eventoFecha->id,
            ],
            [
                'estado' => 'inscripto',
                'datos_capturados' => $validated,
            ]
        );

        return [$inscripcion, $existing === null];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolvePersona(array $validated): Persona
    {
        if (($validated['modo'] ?? null) === 'confirmar_existente' && filled($validated['persona_existente_id'])) {
            /** @var Persona $persona */
            $persona = Persona::query()->findOrFail((int) $validated['persona_existente_id']);

            $persona->fill([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'email' => $validated['email'] ?? null,
                'departamento' => $validated['departamento'] ?? null,
            ]);
            $persona->save();

            return $persona;
        }

        return Persona::query()->create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'email' => $validated['email'] ?? null,
            'departamento' => $validated['departamento'] ?? null,
        ]);
    }
}
