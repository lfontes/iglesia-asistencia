<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummary();
    @endphp

    <div class="mt-6">
        @livewire(
            \App\Filament\Widgets\IpnReporteResumenWidget::class,
            [
                'ipnAulaId' => $this->ipn_aula_id,
                'personaId' => $this->persona_id,
                'desde' => $this->desde,
                'hasta' => $this->hasta,
            ],
            key('ipn-reporte-resumen-' . ($this->ipn_aula_id ?? 'sin-aula') . '-' . ($this->persona_id ?? 'todos') . '-' . ($this->desde ?? 'sin-desde') . '-' . ($this->hasta ?? 'sin-hasta'))
        )
    </div>

    <x-filament::section class="mt-6" icon="heroicon-o-table-cells" icon-color="primary">
        <x-slot name="heading">
            Asistencia por niño
        </x-slot>

        <x-slot name="headerEnd">
            <div class="text-sm text-gray-500 dark:text-gray-300">
                Presentes registrados: <span class="font-semibold text-gray-800 dark:text-white">{{ $summary['total_presentes'] }}</span>
            </div>
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
