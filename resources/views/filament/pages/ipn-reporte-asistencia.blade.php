<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummary();
        $rows = $this->getRows();
        $dates = $this->getDates();
    @endphp

    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Aula</p>
            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $summary['aula']?->nombre ?? 'Sin seleccionar' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Niños</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_ninos'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Encuentros</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_fechas'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Promedio</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-300">{{ $summary['promedio'] }}%</p>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Asistencia por niño</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Matriz de doble entrada por niño y fecha.</p>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-300">
                Presentes registrados: <span class="font-semibold text-gray-800 dark:text-white">{{ $summary['total_presentes'] }}</span>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                Selecciona un aula o registra asistencia IPN para ver el reporte.
            </div>
        @else
            <div class="mt-6 overflow-x-auto rounded-2xl border border-gray-200 dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
                            <th class="sticky left-0 z-20 bg-white px-4 py-3 font-medium dark:!bg-gray-950">Niño</th>
                            <th class="bg-white px-3 py-3 text-center font-medium dark:!bg-gray-950">ID</th>
                            <th class="bg-white px-3 py-3 text-center font-medium dark:!bg-gray-950">%</th>
                            @foreach ($dates as $date)
                                <th class="bg-white px-3 py-3 text-center font-medium dark:!bg-gray-950">
                                    {{ \Illuminate\Support\Carbon::parse($date)->format('d/m') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                        @foreach ($rows as $row)
                            <tr class="align-middle dark:hover:bg-white/5">
                                <td class="sticky left-0 z-10 bg-white px-4 py-4 dark:!bg-gray-950">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $row['nombre_completo'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-300">
                                        {{ $row['edad'] !== null ? $row['edad'] . ' años' : 'Edad sin cargar' }} · {{ $row['responsable'] ?: 'Sin responsable' }}
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-center font-medium text-gray-700 dark:text-gray-200">{{ $row['persona_id'] }}</td>
                                <td class="px-3 py-4 text-center font-semibold text-emerald-700 dark:text-emerald-300">{{ $row['porcentaje'] }}%</td>
                                @foreach ($dates as $date)
                                    @php $state = $row['attendance_by_date'][$date] ?? null; @endphp
                                    <td class="px-3 py-4 text-center dark:text-gray-200">
                                        @if ($state === true)
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                <x-heroicon-o-check-circle class="h-4 w-4" />
                                            </span>
                                        @elseif ($state === false)
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300">
                                                <x-heroicon-o-x-circle class="h-4 w-4" />
                                            </span>
                                        @else
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-300 dark:bg-white/10 dark:text-gray-500">
                                                <span class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-500"></span>
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
