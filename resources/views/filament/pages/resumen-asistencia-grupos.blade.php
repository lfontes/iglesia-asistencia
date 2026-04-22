<x-filament-panels::page>
    {{ $this->form }}

    @php
        $summary = $this->getSummaryData();
        $rows = $this->getAttendanceRows();
        $dates = $this->getAttendanceDates();
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

        @if ($rows->isEmpty())
            <x-filament-tables::empty-state
                heading="Sin datos de asistencia"
                description="Aun no hay participantes o asistencias registradas para este grupo."
                icon="heroicon-o-clipboard-document-list"
            />
        @else
            <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-300">
                            <th class="sticky left-0 z-20 bg-white px-4 py-3 font-medium dark:!bg-gray-950">Persona</th>
                            <th class="bg-white px-3 py-3 text-center font-medium normal-case tracking-normal dark:!bg-gray-950">%</th>
                            @foreach ($dates as $date)
                                <th class="bg-white px-3 py-3 text-center font-medium normal-case tracking-normal dark:!bg-gray-950">
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
                                        {{ $row['presentes'] }} asistencias
                                        @if ($summary['total_fechas'] > 0)
                                            · {{ $row['ausencias'] }} ausencias
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-center font-semibold text-emerald-700 dark:text-emerald-300">
                                    {{ $row['porcentaje'] }}%
                                </td>
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
