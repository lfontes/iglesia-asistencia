<x-filament-panels::page>
    <form wire:submit.prevent="guardar">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Guardar asistencia
        </x-filament::button>
    </form>

    @php
        $integrantesActivos = $this->integrantesActivos();
    @endphp

    @if (filled($this->grupo_id))
        <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Integrantes activos</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Puedes quitar una persona del grupo sin perder el historial de asistencias.</p>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-300">
                    {{ $integrantesActivos->count() }} integrantes
                </div>
            </div>

            @if ($integrantesActivos->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    No hay integrantes activos para este grupo en la fecha seleccionada.
                </div>
            @else
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach ($integrantesActivos as $integrante)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/10">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $integrante['label'] }}</div>

                            <x-filament::button
                                size="xs"
                                color="danger"
                                wire:click="quitarPersonaDelGrupo({{ $integrante['id'] }})"
                                wire:confirm="La persona dejará de figurar como integrante activa del grupo. Sus asistencias históricas se conservarán."
                            >
                                Quitar del grupo
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
