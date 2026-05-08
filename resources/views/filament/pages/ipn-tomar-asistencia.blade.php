<x-filament-panels::page>
    <form wire:submit.prevent="guardar">
        {{ $this->form }}

        @php
            $ninos = $this->ninosActivos();
        @endphp

        @if (filled($this->ipn_aula_id))
            <x-filament::section class="mt-6" icon="heroicon-o-user-group" icon-color="primary">
                <x-slot name="heading">
                    Niños del aula
                </x-slot>

                <x-slot name="description">
                    Marca presentes para la fecha seleccionada.
                </x-slot>

                <x-slot name="headerEnd">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-filament::badge color="gray">
                            {{ $ninos->count() }} niños
                        </x-filament::badge>

                        <x-filament::badge color="success">
                            {{ collect($this->presentes)->filter()->unique()->count() }} presentes
                        </x-filament::badge>
                    </div>
                </x-slot>

                @if ($ninos->isEmpty())
                    <x-filament-tables::empty-state
                        heading="Sin niños activos"
                        description="No hay niños activos en esta aula para la fecha seleccionada."
                        icon="heroicon-o-face-smile"
                    />
                @else
                    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                        <table class="min-w-full table-fixed divide-y divide-gray-100 text-sm dark:divide-white/10">
                            <colgroup>
                                <col class="w-[10%]">
                                <col class="w-[30%]">
                                <col class="w-[15%]">
                                <col class="w-[30%]">
                                <col class="w-[15%]">
                            </colgroup>
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-300">
                                <tr>
                                    <th class="px-5 py-3 text-center">Presente</th>
                                    <th class="px-5 py-3">Niño</th>
                                    <th class="px-5 py-3 text-center">Edad</th>
                                    <th class="px-5 py-3">Responsable</th>
                                    <th class="px-5 py-3 text-center">ID</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                                @foreach ($ninos as $nino)
                                    <tr class="dark:hover:bg-white/5">
                                        <td class="px-5 py-4 text-center">
                                            <input
                                                type="checkbox"
                                                value="{{ $nino['id'] }}"
                                                wire:model="presentes"
                                                class="h-5 w-5 rounded border-gray-300 text-success-600 shadow-sm focus:ring-success-500 dark:border-white/20 dark:bg-white/5"
                                            >
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="font-medium text-gray-950 dark:text-white break-words">
                                                {{ $nino['label'] }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <span class="text-gray-700 dark:text-gray-200">
                                                {{ $nino['edad'] !== null ? $nino['edad'] . ' años' : 'Sin cargar' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            <div class="break-words">
                                                {{ $nino['responsable'] ?: 'Sin responsable' }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <x-filament::badge color="gray" size="sm">
                                                {{ $nino['id'] }}
                                            </x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-check-circle">
                Guardar asistencia IPN
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
