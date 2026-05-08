@php
    /** @var \App\Models\Grupo $record */
    $record = $getRecord();
    $livewire = $getLivewire();
    $facilitadores = $livewire->getFacilitadoresPendientesParaGrupo((int) $record->id);
    $statusBadgeColors = [
        'accepted' => 'info',
        'sent' => 'info',
        'delivered' => 'success',
        'read' => 'success',
        'failed' => 'danger',
        'failed_request' => 'danger',
    ];
    $item = $livewire->getPendienteDataForGrupo((int) $record->id);
@endphp

@if (empty($facilitadores))
    <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">
        Sin facilitadores activos
    </x-filament::badge>
@else
    <div class="space-y-2">
        @foreach ($facilitadores as $facilitador)
            @php
                $telefonoValido = $livewire->facilitadorTieneTelefonoWhatsappValido($facilitador);
                $status = $facilitador['persona_id'] && $item
                    ? $livewire->getReminderStatus(
                        (int) $item['grupo_id'],
                        (int) $facilitador['persona_id'],
                        (string) $item['periodo_inicio'],
                        (string) $item['periodo_fin'],
                    )
                    : null;
            @endphp

            <x-filament::section compact>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $facilitador['nombre'] }}</div>
                    @if (! empty($facilitador['recibe_recordatorios']))
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

                @if ($facilitador['persona_id'] && $item)
                    <div class="mt-2">
                        <x-filament::button
                            size="xs"
                            color="success"
                            :disabled="! $telefonoValido"
                            wire:click="enviarRecordatorioPlantilla({{ (int) $item['grupo_id'] }}, {{ (int) $facilitador['persona_id'] }})"
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
