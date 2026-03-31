<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummaryData();
        $rows = $this->getAttendanceRows();
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
                    Gráfico de barras horizontales según presencia acumulada en el grupo.
                </p>
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
            <div class="mt-6 space-y-4">
                @foreach ($rows as $row)
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="font-medium text-gray-900">{{ $row['nombre_completo'] }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $row['presentes'] }} asistencias de {{ $summary['total_fechas'] }}
                                    @if ($summary['total_fechas'] > 0)
                                        · {{ $row['ausencias'] }} ausencias
                                    @endif
                                    @if ($row['ultima_asistencia'])
                                        · Última asistencia: {{ \Illuminate\Support\Carbon::parse($row['ultima_asistencia'])->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>

                            <div class="text-sm font-semibold text-emerald-700">
                                {{ $row['porcentaje'] }}%
                            </div>
                        </div>

                        <div class="mt-3 h-4 overflow-hidden rounded-full" style="background-color: #e5e7eb;">
                            <div
                                class="h-full rounded-full transition-all"
                                style="width: {{ max(0, min(100, (int) $row['porcentaje'])) }}%; background: linear-gradient(90deg, #10b981 0%, #34d399 100%);"
                            ></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
