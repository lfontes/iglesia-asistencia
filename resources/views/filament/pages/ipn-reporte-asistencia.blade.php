<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummary();
        $rows = $this->getRows();
        $dates = $this->getDates();
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

        @if ($rows->isEmpty())
            <x-filament-tables::empty-state
                heading="Sin datos de asistencia"
                description="Selecciona un aula o registra asistencia IPN para ver el reporte."
                icon="heroicon-o-clipboard-document-list"
            />
        @else
            <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
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
                                            <span
                                                aria-label="Presente"
                                                title="Presente"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full"
                                                style="background-color: #dcfce7; border: 1px solid #bbf7d0; color: #16a34a;"
                                            >
                                                <span aria-hidden="true" style="font-size: 1rem; font-weight: 700; line-height: 1;">✓</span>
                                            </span>
                                        @elseif ($state === false)
                                            <span
                                                aria-label="Ausente"
                                                title="Ausente"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full"
                                                style="background-color: #ffe4e6; border: 1px solid #fecdd3; color: #e11d48;"
                                            >
                                                <span aria-hidden="true" style="font-size: 1rem; font-weight: 700; line-height: 1;">×</span>
                                            </span>
                                        @else
                                            <span
                                                aria-label="Sin registro"
                                                title="Sin registro"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full"
                                                style="background-color: #f3f4f6; border: 1px solid #e5e7eb; color: #9ca3af;"
                                            >
                                                <span aria-hidden="true" style="font-size: 1rem; font-weight: 700; line-height: 1;">·</span>
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
    </x-filament::section>
</x-filament-panels::page>
