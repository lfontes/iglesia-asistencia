<x-filament-panels::page>
    @if (count($pares) === 0)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-filament::icon
                icon="heroicon-o-check-circle"
                class="h-12 w-12 text-success-500 mb-4"
            />
            <p class="text-lg font-medium text-gray-900 dark:text-white">No se encontraron posibles duplicados</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Todos los registros parecen únicos.</p>
        </div>
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ count($pares) }} {{ count($pares) === 1 ? 'par encontrado' : 'pares encontrados' }}
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10 w-full">
            <table class="w-full text-sm divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 w-2/5 border-r border-gray-200 dark:border-white/10">Persona A</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 w-2/5 border-r border-gray-200 dark:border-white/10">Persona B</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400 w-1/5">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10 bg-white dark:bg-gray-900">
                    @foreach ($pares as $par)
                        <tr wire:key="par-{{ $par['id_a'] }}-{{ $par['id_b'] }}">
                            <td class="px-4 py-3 border-r border-gray-200 dark:border-white/10">
                                <a
                                    href="{{ $this->getEditUrl($par['id_a']) }}"
                                    target="_blank"
                                    class="font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                >
                                    {{ $par['apellido_a'] }}, {{ $par['nombre_a'] }}
                                </a>
                                @if ($par['telefono_a'])
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $par['telefono_a'] }}</div>
                                @endif
                                <div class="text-xs text-gray-300 dark:text-gray-600">ID {{ $par['id_a'] }}</div>
                            </td>
                            <td class="px-4 py-3 border-r border-gray-200 dark:border-white/10">
                                <a
                                    href="{{ $this->getEditUrl($par['id_b']) }}"
                                    target="_blank"
                                    class="font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                >
                                    {{ $par['apellido_b'] }}, {{ $par['nombre_b'] }}
                                </a>
                                @if ($par['telefono_b'])
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $par['telefono_b'] }}</div>
                                @endif
                                <div class="text-xs text-gray-300 dark:text-gray-600">ID {{ $par['id_b'] }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        wire:click="ignorarPar({{ $par['id_a'] }}, {{ $par['id_b'] }})"
                                    >
                                        Ignorar
                                    </x-filament::button>
                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        wire:click="abrirFusionar({{ $par['id_a'] }}, {{ $par['id_b'] }})"
                                    >
                                        Fusionar
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
