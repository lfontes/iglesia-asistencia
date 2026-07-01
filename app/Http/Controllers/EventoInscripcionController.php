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
        $this->ensureCaptchaChallenge($eventoFecha);

        return view('eventos.inscripcion', [
            'eventoFecha' => $eventoFecha->load('evento'),
            'candidates' => collect(),
            'departamentos' => Persona::departamentosMendoza(),
            'input' => old(),
            'captchaQuestion' => session($this->captchaQuestionKey($eventoFecha)),
        ]);
    }

    public function store(Request $request, EventoFecha $eventoFecha, PersonaMatchingService $matchingService): View|RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date'],
            'telefono' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'departamento' => ['required', 'string', 'max:100', Rule::in(array_keys(Persona::departamentosMendoza()))],
            'modo' => ['nullable', 'in:confirmar_existente,crear_nueva'],
            'persona_existente_id' => ['nullable', 'integer'],
        ]);

        if (! $this->captchaAlreadyPassed($request, $eventoFecha)) {
            if (! $this->captchaIsValid($request, $eventoFecha)) {
                $this->regenerateCaptchaChallenge($eventoFecha);

                return redirect()
                    ->back()
                    ->withErrors(['captcha_answer' => 'La respuesta de verificación no es correcta. Inténtalo nuevamente.'])
                    ->withInput();
            }

            $request->session()->put($this->captchaPassedKey($eventoFecha), true);
        }

        if (($validated['modo'] ?? null) === null) {
            $candidates = $matchingService->findCandidates($validated);

            if ($candidates->isNotEmpty()) {
                return view('eventos.inscripcion', [
                    'eventoFecha' => $eventoFecha->load('evento'),
                    'candidates' => $candidates,
                    'departamentos' => Persona::departamentosMendoza(),
                    'input' => $validated,
                    'captchaQuestion' => null,
                ]);
            }
        }

        $persona = $this->resolvePersona($validated);
        [, $created] = $this->registrarInscripcion($persona, $eventoFecha, $validated);

        $message = $created
            ? 'Tu inscripción quedó registrada correctamente.'
            : 'Ya estabas inscripto para esta fecha. Actualizamos tus datos.';

        $this->clearCaptchaState($request, $eventoFecha);

        return redirect()
            ->route('eventos.inscripcion.create', $eventoFecha)
            ->with('success', $message);
    }

    public function cancelarBuscar(Request $request, EventoFecha $eventoFecha): View
    {
        $this->ensureCaptchaChallenge($eventoFecha);

        $telefono = (string) $request->input('telefono', '');
        $normalized = $this->normalizePhone($telefono);

        $inscripcion = null;

        if ($normalized !== null) {
            $inscripcion = EventoInscripcion::query()
                ->where('evento_fecha_id', $eventoFecha->id)
                ->where('estado', 'inscripto')
                ->whereHas('persona', fn ($query) => $query->where('telefono_normalizado', $normalized))
                ->with('persona:id,nombre,apellido')
                ->first();
        }

        return view('eventos.inscripcion', [
            'eventoFecha' => $eventoFecha->load('evento'),
            'candidates' => collect(),
            'departamentos' => Persona::departamentosMendoza(),
            'input' => old(),
            'captchaQuestion' => session($this->captchaQuestionKey($eventoFecha)),
            'cancelacion' => $inscripcion,
            'cancelacionTelefono' => $telefono,
            'cancelacionError' => $inscripcion === null
                ? 'No encontramos ninguna inscripción activa con ese teléfono para este evento.'
                : null,
        ]);
    }

    public function cancelar(Request $request, EventoFecha $eventoFecha): RedirectResponse
    {
        $validated = $request->validate([
            'inscripcion_id' => ['required', 'integer'],
        ]);

        $inscripcion = EventoInscripcion::query()
            ->where('id', $validated['inscripcion_id'])
            ->where('evento_fecha_id', $eventoFecha->id)
            ->where('estado', 'inscripto')
            ->first();

        if ($inscripcion === null) {
            return redirect()
                ->route('eventos.inscripcion.create', $eventoFecha)
                ->with('cancelacion_error', 'No pudimos cancelar la inscripción. Es posible que ya estuviera cancelada.');
        }

        $inscripcion->update(['estado' => 'cancelado']);

        return redirect()
            ->route('eventos.inscripcion.create', $eventoFecha)
            ->with('success', 'Tu inscripción fue cancelada. ¡Esperamos verte en otra oportunidad!');
    }

    protected function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' && $digits !== null ? $digits : null;
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

    protected function ensureCaptchaChallenge(EventoFecha $eventoFecha): void
    {
        if (session()->has($this->captchaQuestionKey($eventoFecha)) && session()->has($this->captchaAnswerKey($eventoFecha))) {
            return;
        }

        $this->regenerateCaptchaChallenge($eventoFecha);
    }

    protected function regenerateCaptchaChallenge(EventoFecha $eventoFecha): void
    {
        $first = random_int(2, 9);
        $second = random_int(2, 9);

        session()->put($this->captchaQuestionKey($eventoFecha), "{$first} + {$second}");
        session()->put($this->captchaAnswerKey($eventoFecha), $first + $second);
        session()->forget($this->captchaPassedKey($eventoFecha));
    }

    protected function captchaIsValid(Request $request, EventoFecha $eventoFecha): bool
    {
        $expected = session($this->captchaAnswerKey($eventoFecha));
        $answer = $request->input('captcha_answer');

        return $expected !== null
            && is_numeric($answer)
            && (int) $answer === (int) $expected;
    }

    protected function captchaAlreadyPassed(Request $request, EventoFecha $eventoFecha): bool
    {
        return $request->session()->get($this->captchaPassedKey($eventoFecha)) === true;
    }

    protected function clearCaptchaState(Request $request, EventoFecha $eventoFecha): void
    {
        $request->session()->forget([
            $this->captchaQuestionKey($eventoFecha),
            $this->captchaAnswerKey($eventoFecha),
            $this->captchaPassedKey($eventoFecha),
        ]);
    }

    protected function captchaQuestionKey(EventoFecha $eventoFecha): string
    {
        return "evento_inscripcion.{$eventoFecha->id}.captcha_question";
    }

    protected function captchaAnswerKey(EventoFecha $eventoFecha): string
    {
        return "evento_inscripcion.{$eventoFecha->id}.captcha_answer";
    }

    protected function captchaPassedKey(EventoFecha $eventoFecha): string
    {
        return "evento_inscripcion.{$eventoFecha->id}.captcha_passed";
    }
}
