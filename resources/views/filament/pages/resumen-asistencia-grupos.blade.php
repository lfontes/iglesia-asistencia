<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummaryData();
    @endphp

    <div class="mt-6">
        @livewire(
            \App\Filament\Widgets\ResumenAsistenciaGruposWidget::class,
            [
                'grupoId' => $this->grupo_id,
                'personaId' => $this->persona_id,
            ],
            key('resumen-asistencia-grupos-' . ($this->grupo_id ?? 'sin-grupo') . '-' . ($this->persona_id ?? 'todos'))
        )
    </div>

    <x-filament::section class="mt-6" icon="heroicon-o-table-cells" icon-color="primary">
        <x-slot name="heading">
            Asistencia por persona
        </x-slot>

        <x-slot name="headerEnd">
            <div class="text-right text-sm text-gray-500 dark:text-gray-300">
                Total de presentes registrados: <span class="font-semibold text-gray-800 dark:text-white">{{ $summary['total_presentes'] }}</span>
            </div>
        </x-slot>

        @if ($this->getFocusedPersonaName())
            <div class="mb-4 text-sm font-medium text-primary-700 dark:text-primary-400">
                Mostrando asistencia de {{ $this->getFocusedPersonaName() }}.
            </div>
        @endif

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
