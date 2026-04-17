<x-filament-panels::page>
    @php($rows = $this->getRows())

    <div class="space-y-6">
        <div class="rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="px-4 py-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Grupos ministeriales liderados</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-300">
                            Aquí puedes seguir qué integrantes de tus grupos ya están en crecimiento.
                        </p>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-300">
                        Total: {{ $rows->count() }}
                    </div>
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    No tienes grupos ministeriales asignados todavía.
                </div>
            @else
                <div class="mt-6 overflow-x-auto px-10 pb-6">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-300">
                                <th class="px-4 py-3 font-medium">Grupo</th>
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 font-medium">Integrantes activos</th>
                                <th class="px-4 py-3 font-medium">En crecimiento</th>
                                <th class="px-4 py-3 font-medium">Sin crecimiento</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-4 font-medium text-gray-800 dark:text-white">{{ $row['grupo']->nombre }}</td>
                                    <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ $row['grupo']->tipoGrupo?->nombre ?? '-' }}</td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-200">{{ $row['integrantes_activos'] }}</td>
                                    <td class="px-4 py-4 text-emerald-700 dark:text-emerald-300">{{ $row['en_crecimiento'] }}</td>
                                    <td class="px-4 py-4 text-amber-700 dark:text-amber-300">{{ $row['sin_crecimiento'] }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ $this->getSummaryUrl($row['grupo']) }}" class="font-medium text-primary-600 hover:text-primary-500">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
