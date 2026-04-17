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
        $statusColors = [
            'accepted' => 'bg-blue-100 text-blue-800',
            'sent' => 'bg-sky-100 text-sky-800',
            'delivered' => 'bg-emerald-100 text-emerald-800',
            'read' => 'bg-green-100 text-green-800',
            'failed' => 'bg-rose-100 text-rose-800',
            'failed_request' => 'bg-rose-100 text-rose-800',
        ];
    @endphp

    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Grupos pendientes</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_grupos'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Facilitadores detectados</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_facilitadores'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Sin teléfono</p>
            <p class="mt-2 text-3xl font-semibold text-amber-600">{{ $summary['sin_telefono'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Frecuencias</p>
            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                Semanales: {{ $summary['semanales'] }} · Quincenales: {{ $summary['quincenales'] }} · Mensuales: {{ $summary['mensuales'] }}
            </p>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Grupos con asistencia pendiente</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Base para futuros recordatorios por WhatsApp a facilitadores.</p>
            </div>
        </div>

        @if ($this->getBulkDispatchStatusLabel())
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                {{ $this->getBulkDispatchStatusLabel() }}
            </div>
        @endif

        @if ($pendientes->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                No hay grupos con asistencia pendiente para la fecha de referencia seleccionada.
            </div>
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-300">
                            <th class="px-4 py-3 font-medium">Grupo</th>
                            <th class="px-4 py-3 font-medium">Frecuencia</th>
                            <th class="px-4 py-3 font-medium">Período evaluado</th>
                            <th class="px-4 py-3 font-medium">Última asistencia</th>
                            <th class="px-4 py-3 font-medium">Facilitadores</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($pendientes as $item)
                            <tr class="align-top">
                                <td class="px-4 py-4 font-medium">
                                    <a
                                        href="{{ \App\Filament\Pages\ResumenAsistenciaGrupos::getUrl(['grupo_id' => $item['grupo_id']]) }}"
                                        class="text-primary-600 hover:text-primary-500 hover:underline"
                                    >
                                        {{ $item['grupo'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-200">{{ $frecuenciaLabels[$item['frecuencia']] ?? ucfirst($item['frecuencia']) }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-200">
                                    {{ \Illuminate\Support\Carbon::parse($item['periodo_inicio'])->format('d/m/Y') }}
                                    -
                                    {{ \Illuminate\Support\Carbon::parse($item['periodo_fin'])->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-200">
                                    {{ $item['ultima_asistencia'] ? \Illuminate\Support\Carbon::parse($item['ultima_asistencia'])->format('d/m/Y') : 'Sin asistencias registradas' }}
                                </td>
                                <td class="px-4 py-4">
                                    @if (empty($item['facilitadores']))
                                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                                Sin facilitadores activos
                                            </span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach ($item['facilitadores'] as $facilitador)
                                                @php
                                                    $telefonoValido = $this->facilitadorTieneTelefonoWhatsappValido($facilitador);
                                                    $status = $facilitador['persona_id']
                                                        ? $this->getReminderStatus(
                                                            $item['grupo_id'],
                                                            $facilitador['persona_id'],
                                                            $item['periodo_inicio'],
                                                            $item['periodo_fin'],
                                                        )
                                                        : null;
                                                @endphp
                                                <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <div class="font-medium text-gray-900 dark:text-white">{{ $facilitador['nombre'] }}</div>
                                                        @if (!empty($facilitador['recibe_recordatorios']))
                                                            <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-500/15 dark:text-blue-300">
                                                                Recibe recordatorios
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-gray-600 dark:text-gray-300">
                                                        {{ $facilitador['telefono'] ?: 'Sin teléfono cargado' }}
                                                    </div>
                                                    @if ($status)
                                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                            <span class="inline-flex rounded-full px-2 py-1 font-medium {{ $statusColors[$status['status']] ?? 'bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-gray-300' }}">
                                                                {{ $status['status'] }}
                                                            </span>
                                                            @if ($status['updated_at'])
                                                                <span class="text-gray-500 dark:text-gray-400">{{ $status['updated_at'] }}</span>
                                                            @endif
                                                        </div>
                                                        @if ($status['error_message'])
                                                            <div class="mt-1 text-xs text-rose-700">
                                                                {{ $status['error_message'] }}
                                                            </div>
                                                        @endif
                                                    @endif
                                                    @if ($facilitador['persona_id'])
                                                        <div class="mt-2">
                                                            <x-filament::button
                                                                size="xs"
                                                                color="success"
                                                                :disabled="! $telefonoValido"
                                                                wire:click="enviarRecordatorioPlantilla({{ $item['grupo_id'] }}, {{ $facilitador['persona_id'] }})"
                                                            >
                                                                Enviar recordatorio
                                                            </x-filament::button>
                                                            @unless ($telefonoValido)
                                                                <div class="mt-1 text-xs text-amber-700">
                                                                    Falta un teléfono válido para WhatsApp.
                                                                </div>
                                                            @endunless
                                                        </div>
                                                    @endif
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
