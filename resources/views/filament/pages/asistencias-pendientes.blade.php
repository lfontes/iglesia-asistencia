<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummary();
        $pendientes = $this->getPendientes();
        $frecuenciaLabels = [
            \App\Models\Grupo::FRECUENCIA_SEMANAL => 'Semanal',
            \App\Models\Grupo::FRECUENCIA_QUINCENAL => 'Quincenal',
            \App\Models\Grupo::FRECUENCIA_MENSUAL => 'Mensual',
        ];
    @endphp

    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Grupos pendientes</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $summary['total_grupos'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Facilitadores detectados</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $summary['total_facilitadores'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Sin teléfono</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600">{{ $summary['sin_telefono'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Frecuencias</p>
            <p class="mt-2 text-sm font-semibold text-gray-900">
                Semanales: {{ $summary['semanales'] }} · Quincenales: {{ $summary['quincenales'] }} · Mensuales: {{ $summary['mensuales'] }}
            </p>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Grupos con asistencia pendiente</h2>
                <p class="text-sm text-gray-500">Base para futuros recordatorios por WhatsApp a facilitadores.</p>
            </div>
        </div>

        @if ($pendientes->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                No hay grupos con asistencia pendiente para la fecha de referencia seleccionada.
            </div>
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">Grupo</th>
                            <th class="px-4 py-3 font-medium">Frecuencia</th>
                            <th class="px-4 py-3 font-medium">Período evaluado</th>
                            <th class="px-4 py-3 font-medium">Última asistencia</th>
                            <th class="px-4 py-3 font-medium">Facilitadores</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pendientes as $item)
                            <tr class="align-top">
                                <td class="px-4 py-4 font-medium text-gray-900">{{ $item['grupo'] }}</td>
                                <td class="px-4 py-4 text-gray-700">{{ $frecuenciaLabels[$item['frecuencia']] ?? ucfirst($item['frecuencia']) }}</td>
                                <td class="px-4 py-4 text-gray-700">
                                    {{ \Illuminate\Support\Carbon::parse($item['periodo_inicio'])->format('d/m/Y') }}
                                    -
                                    {{ \Illuminate\Support\Carbon::parse($item['periodo_fin'])->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-4 text-gray-700">
                                    {{ $item['ultima_asistencia'] ? \Illuminate\Support\Carbon::parse($item['ultima_asistencia'])->format('d/m/Y') : 'Sin asistencias registradas' }}
                                </td>
                                <td class="px-4 py-4">
                                    @if (empty($item['facilitadores']))
                                        <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                                            Sin facilitadores activos
                                        </span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach ($item['facilitadores'] as $facilitador)
                                                <div class="rounded-xl bg-gray-50 px-3 py-2">
                                                    <div class="font-medium text-gray-900">{{ $facilitador['nombre'] }}</div>
                                                    <div class="text-gray-600">
                                                        {{ $facilitador['telefono'] ?: 'Sin teléfono cargado' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
