<x-filament-panels::page>
    {{ $this->form }}

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

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
