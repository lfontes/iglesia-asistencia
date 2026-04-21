<x-filament-panels::page>
    <form wire:submit.prevent="guardar">
        {{ $this->form }}

        @php
            $ninos = $this->ninosActivos();
        @endphp

        @if (filled($this->ipn_aula_id))
            <div class="mt-6 overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Niños del aula</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-300">Marca presentes para la fecha seleccionada.</p>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-300">{{ $ninos->count() }} niños</div>
                </div>

                @if ($ninos->isEmpty())
                    <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-300">
                        No hay niños activos en esta aula para la fecha seleccionada.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3">Presente</th>
                                    <th class="px-4 py-3">Persona ID</th>
                                    <th class="px-4 py-3">Niño</th>
                                    <th class="px-4 py-3">Edad</th>
                                    <th class="px-4 py-3">Responsable</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($ninos as $nino)
                                    <tr class="dark:hover:bg-white/5">
                                        <td class="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                value="{{ $nino['id'] }}"
                                                wire:model="presentes"
                                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                            >
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $nino['id'] }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $nino['label'] }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $nino['edad'] !== null ? $nino['edad'] . ' años' : '-' }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $nino['responsable'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <x-filament::button type="submit" class="mt-4">
            Guardar asistencia IPN
        </x-filament::button>
    </form>
</x-filament-panels::page>
