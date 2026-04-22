<x-filament-panels::page>
    {{ $this->form }}

    @php
        $pendientes = $this->getPendientes();
        $frecuenciaLabels = [
            \App\Models\Grupo::FRECUENCIA_SEMANAL => 'Semanal',
            \App\Models\Grupo::FRECUENCIA_QUINCENAL => 'Quincenal',
            \App\Models\Grupo::FRECUENCIA_MENSUAL => 'Mensual',
        ];
        $statusBadgeColors = [
            'accepted' => 'info',
            'sent' => 'info',
            'delivered' => 'success',
            'read' => 'success',
            'failed' => 'danger',
            'failed_request' => 'danger',
        ];
    @endphp

    <div class="mt-6">
        @livewire(
            \App\Filament\Widgets\AsistenciasPendientesResumenWidget::class,
            ['fecha' => $this->fecha],
            key('asistencias-pendientes-resumen-' . ($this->fecha ?? 'sin-fecha'))
        )
    </div>

    <x-filament::section class="mt-6" icon="heroicon-o-chat-bubble-left-right" icon-color="primary">
        <x-slot name="heading">
            Grupos con asistencia pendiente
        </x-slot>

        @if ($this->getBulkDispatchStatusLabel())
            <x-filament::section compact icon="heroicon-o-exclamation-triangle" icon-color="warning" class="mb-4">
                {{ $this->getBulkDispatchStatusLabel() }}
            </x-filament::section>
        @endif

        @if ($pendientes->isEmpty())
            <x-filament-tables::empty-state
                heading="Sin asistencias pendientes"
                description="No hay grupos con asistencia pendiente para la fecha de referencia seleccionada."
                icon="heroicon-o-check-circle"
            />
        @else
            <div class="overflow-x-auto">
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
                                        <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">
                                            Sin facilitadores activos
                                        </x-filament::badge>
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
                                                <x-filament::section compact>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <div class="font-medium text-gray-900 dark:text-white">{{ $facilitador['nombre'] }}</div>
                                                        @if (!empty($facilitador['recibe_recordatorios']))
                                                            <x-filament::badge color="info" size="sm" icon="heroicon-o-bell-alert">
                                                                Recibe recordatorios
                                                            </x-filament::badge>
                                                        @endif
                                                    </div>
                                                    <div class="text-gray-600 dark:text-gray-300">
                                                        {{ $facilitador['telefono'] ?: 'Sin teléfono cargado' }}
                                                    </div>
                                                    @if ($status)
                                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                            <x-filament::badge :color="$statusBadgeColors[$status['status']] ?? 'gray'" size="sm">
                                                                {{ $status['status'] }}
                                                            </x-filament::badge>
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
                                                                <div class="mt-1 text-xs text-warning-700 dark:text-warning-400">
                                                                    Falta un teléfono válido para WhatsApp.
                                                                </div>
                                                            @endunless
                                                        </div>
                                                    @endif
                                                </x-filament::section>
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
    </x-filament::section>
</x-filament-panels::page>
