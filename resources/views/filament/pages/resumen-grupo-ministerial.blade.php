<x-filament-panels::page>
    @php
        $grupo = $this->getGrupo();
        $summary = $this->getSummary();
        $rows = $this->getPeopleRows();
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-sm text-gray-500 dark:text-gray-300">Integrantes activos</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_personas'] }}</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <div class="text-sm text-emerald-700 dark:text-emerald-300">En crecimiento</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-900 dark:text-emerald-200">{{ $summary['en_crecimiento'] }}</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="text-sm text-amber-700 dark:text-amber-300">Sin crecimiento</div>
                <div class="mt-2 text-2xl font-semibold text-amber-900 dark:text-amber-200">{{ $summary['sin_crecimiento'] }}</div>
            </div>
        </div>

        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $grupo?->nombre }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">
                        Tipo: {{ $grupo?->tipoGrupo?->nombre ?? 'Sin tipo' }}
                    </p>
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    Este grupo todavía no tiene integrantes activos.
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-300">
                                <th class="px-4 py-3 font-medium">Persona</th>
                                <th class="px-4 py-3 font-medium">Teléfono</th>
                                <th class="px-4 py-3 font-medium">Crecimiento</th>
                                <th class="px-4 py-3 font-medium">% asistencia</th>
                                <th class="px-4 py-3 font-medium">Grupo(s) de crecimiento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($rows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-4 text-gray-800 dark:text-white">
                                        @if ($this->getGrowthAttendanceUrl($row))
                                            <a href="{{ $this->getGrowthAttendanceUrl($row) }}" class="font-medium text-primary-600 hover:text-primary-500">
                                                {{ $row['nombre'] }}
                                            </a>
                                        @else
                                            {{ $row['nombre'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ $row['telefono'] ?: '-' }}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $row['en_crecimiento'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' }}">
                                            {{ $row['en_crecimiento'] ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-200">
                                        {{ $row['porcentaje_asistencia_crecimiento'] !== null ? $row['porcentaje_asistencia_crecimiento'] . '%' : '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-200">{{ $row['grupos_crecimiento'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
