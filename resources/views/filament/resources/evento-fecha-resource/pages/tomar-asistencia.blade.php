<x-filament-panels::page>
    <x-filament::page>
        @php($inscriptos = $this->getInscriptos())

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-sm text-stone-500 dark:text-gray-300">Inscriptos</div>
                <div class="mt-2 text-2xl font-semibold text-stone-900 dark:text-white">{{ $this->getTotalInscriptos() }}</div>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-sm text-stone-500 dark:text-gray-300">Presentes</div>
                <div class="mt-2 text-2xl font-semibold text-stone-900 dark:text-white">{{ $this->getTotalPresentes() }}</div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <div class="text-sm text-emerald-700 dark:text-emerald-300">Inscriptos presentes</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-800 dark:text-emerald-200">{{ $this->getTotalInscriptosPresentes() }}</div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="text-sm text-amber-700 dark:text-amber-300">Presentes sin inscripción</div>
                <div class="mt-2 text-2xl font-semibold text-amber-800 dark:text-amber-200">{{ $this->getTotalPresentesNoInscriptos() }}</div>
            </div>
        </div>

        <div class="mb-6 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Inscriptos</h2>
                    <p class="text-sm text-stone-500 dark:text-gray-300">
                        Personas registradas previamente para esta fecha de evento.
                    </p>
                </div>

                <a
                    href="{{ $this->getFormularioInscripcionUrl() }}"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-900"
                >
                    Abrir formulario público
                </a>
            </div>

            @if ($inscriptos->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-5 py-4 text-sm text-stone-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    Todavía no hay inscripciones para esta fecha.
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm dark:divide-white/10">
                        <thead class="bg-stone-50 text-left text-stone-500 dark:bg-white/5 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">Persona</th>
                                <th class="px-4 py-3 font-medium">Teléfono</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 text-right font-medium">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 bg-white dark:divide-white/10 dark:bg-transparent">
                            @foreach ($inscriptos as $inscripto)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-stone-900 dark:text-white">
                                        {{ trim($inscripto->persona->apellido . ' ' . $inscripto->persona->nombre) }}
                                    </td>
                                    <td class="px-4 py-3 text-stone-600 dark:text-gray-300">{{ $inscripto->persona->telefono ?: '-' }}</td>
                                    <td class="px-4 py-3 text-stone-600 dark:text-gray-300">{{ $inscripto->persona->email ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $this->isInscriptoPresente((int) $inscripto->persona_id),
                                            'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' => ! $this->isInscriptoPresente((int) $inscripto->persona_id),
                                        ])>
                                            @if ($this->isInscriptoPresente((int) $inscripto->persona_id))
                                                <x-heroicon-o-check-circle class="h-4 w-4" />
                                                Presente
                                            @else
                                                <x-heroicon-o-x-circle class="h-4 w-4" />
                                                Ausente
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($this->isInscriptoPresente((int) $inscripto->persona_id))
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                wire:click="quitarInscriptoPresente({{ (int) $inscripto->persona_id }})"
                                            >
                                                Quitar presente
                                            </x-filament::button>
                                        @else
                                            <x-filament::button
                                                size="xs"
                                                color="success"
                                                wire:click="marcarInscriptoPresente({{ (int) $inscripto->persona_id }})"
                                            >
                                                Marcar presente
                                            </x-filament::button>
                                        @endif
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
