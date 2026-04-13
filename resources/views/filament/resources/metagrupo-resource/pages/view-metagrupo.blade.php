<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $rows = $this->getPeopleRows();
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">Grupos</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['total_grupos'] }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">Personas únicas</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['total_personas'] }}</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <div class="text-sm text-emerald-700">En crecimiento</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ $summary['en_crecimiento'] }}</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <div class="text-sm text-amber-700">Sin crecimiento</div>
                <div class="mt-2 text-2xl font-semibold text-amber-900">{{ $summary['sin_crecimiento'] }}</div>
            </div>
        </div>

        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $this->record->nombre }}</h2>
                    <p class="text-sm text-gray-500">
                        Líder:
                        {{ $this->record->lider ? trim($this->record->lider->apellido . ' ' . $this->record->lider->nombre) : 'Sin asignar' }}
                    </p>
                    @if (filled($this->record->descripcion))
                        <p class="mt-2 text-sm text-gray-600">{{ $this->record->descripcion }}</p>
                    @endif
                </div>
                <div class="text-sm text-gray-500">
                    Grupos incluidos: {{ $this->record->grupos->pluck('nombre')->implode(', ') ?: 'Sin grupos' }}
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                    Este metagrupo todavía no tiene personas activas en sus grupos.
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">Persona</th>
                                <th class="px-4 py-3 font-medium">Teléfono</th>
                                <th class="px-4 py-3 font-medium">Grupos del metagrupo</th>
                                <th class="px-4 py-3 font-medium">Crecimiento</th>
                                <th class="px-4 py-3 font-medium">Grupo(s) de crecimiento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-4 text-gray-800">
                                        @if ($this->getGrowthAttendanceUrl($row))
                                            <a
                                                href="{{ $this->getGrowthAttendanceUrl($row) }}"
                                                class="font-medium text-primary-600 hover:text-primary-500"
                                            >
                                                {{ $row['nombre'] }}
                                            </a>
                                        @else
                                            {{ $row['nombre'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-gray-600">{{ $row['telefono'] ?: '-' }}</td>
                                    <td class="px-4 py-4 text-gray-700">{{ $row['grupos_metagrupo'] }}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $row['en_crecimiento'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $row['en_crecimiento'] ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700">{{ $row['grupos_crecimiento'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
