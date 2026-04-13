<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummaryData();
        $rows = $this->getAttendanceRows();
        $dates = $this->getAttendanceDates();
    @endphp

    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Grupo</p>
            <p class="mt-2 text-sm font-semibold text-gray-900">
                {{ $summary['grupo']?->nombre ?? 'Sin seleccionar' }}
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Participantes</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $summary['total_personas'] }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Reuniones registradas</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $summary['total_fechas'] }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Promedio de asistencia</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ $summary['promedio_asistencia'] }}%</p>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Asistencia por persona</h2>
                <p class="text-sm text-gray-500">
                    Matriz de doble entrada por persona y fecha de encuentro.
                </p>
                @if ($this->getFocusedPersonaName())
                    <p class="mt-2 text-sm font-medium text-primary-700">
                        Mostrando asistencia de {{ $this->getFocusedPersonaName() }}.
                    </p>
                @endif
            </div>

            <div class="text-right text-sm text-gray-500">
                Total de presentes registrados: <span class="font-semibold text-gray-800">{{ $summary['total_presentes'] }}</span>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                Aun no hay participantes o asistencias registradas para este grupo.
            </div>
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-4 py-3 font-medium">Persona</th>
                            <th class="bg-white px-3 py-3 text-center font-medium normal-case tracking-normal">%</th>
                            @foreach ($dates as $date)
                                <th class="bg-white px-3 py-3 text-center font-medium normal-case tracking-normal">
                                    {{ \Illuminate\Support\Carbon::parse($date)->format('d/m') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            <tr class="align-middle">
                                <td class="sticky left-0 z-10 bg-white px-4 py-4">
                                    <div class="font-medium text-gray-900">{{ $row['nombre_completo'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ $row['presentes'] }} asistencias
                                        @if ($summary['total_fechas'] > 0)
                                            · {{ $row['ausencias'] }} ausencias
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-center font-semibold text-emerald-700">
                                    {{ $row['porcentaje'] }}%
                                </td>
                                @foreach ($dates as $date)
                                    @php $state = $row['attendance_by_date'][$date] ?? null; @endphp
                                    <td class="px-3 py-4 text-center">
                                        @if ($state === true)
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                                <x-heroicon-o-check-circle class="h-4 w-4" />
                                            </span>
                                        @elseif ($state === false)
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                                <x-heroicon-o-x-circle class="h-4 w-4" />
                                            </span>
                                        @else
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
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
