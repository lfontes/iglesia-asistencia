<x-filament-panels::page>
    <x-filament::page>
        @php($inscriptos = $this->getInscriptos())

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <div class="text-sm text-stone-500">Inscriptos</div>
                <div class="mt-2 text-2xl font-semibold text-stone-900">{{ $this->getTotalInscriptos() }}</div>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <div class="text-sm text-stone-500">Presentes</div>
                <div class="mt-2 text-2xl font-semibold text-stone-900">{{ $this->getTotalPresentes() }}</div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 shadow-sm">
                <div class="text-sm text-emerald-700">Inscriptos presentes</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-800">{{ $this->getTotalInscriptosPresentes() }}</div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-sm">
                <div class="text-sm text-amber-700">Presentes sin inscripción</div>
                <div class="mt-2 text-2xl font-semibold text-amber-800">{{ $this->getTotalPresentesNoInscriptos() }}</div>
            </div>
        </div>

        <div class="mb-6 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-stone-900">Inscriptos</h2>
                    <p class="text-sm text-stone-500">
                        Personas registradas previamente para esta fecha de evento.
                    </p>
                </div>

                <a
                    href="{{ $this->getFormularioInscripcionUrl() }}"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white"
                >
                    Abrir formulario público
                </a>
            </div>

            @if ($inscriptos->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-5 py-4 text-sm text-stone-500">
                    Todavía no hay inscripciones para esta fecha.
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-stone-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Persona</th>
                                <th class="px-4 py-3 font-medium">Teléfono</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 bg-white">
                            @foreach ($inscriptos as $inscripto)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-stone-900">
                                        {{ trim($inscripto->persona->apellido . ' ' . $inscripto->persona->nombre) }}
                                    </td>
                                    <td class="px-4 py-3 text-stone-600">{{ $inscripto->persona->telefono ?: '-' }}</td>
                                    <td class="px-4 py-3 text-stone-600">{{ $inscripto->persona->email ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800' => $this->isInscriptoPresente((int) $inscripto->persona_id),
                                            'bg-amber-100 text-amber-800' => ! $this->isInscriptoPresente((int) $inscripto->persona_id),
                                        ])>
                                            {{ $this->isInscriptoPresente((int) $inscripto->persona_id) ? 'Presente' : 'Pendiente' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <form wire:submit.prevent="guardar">
            {{ $this->form }}

            <x-filament::button type="submit" class="mt-4">
                Guardar asistencia
            </x-filament::button>
        </form>
    </x-filament::page>
</x-filament-panels::page>
