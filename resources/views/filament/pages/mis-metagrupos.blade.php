<x-filament-panels::page>
    @php($rows = $this->getRows())

    <div class="space-y-6">
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="px-4 py-6">
                    <h2 class="text-lg font-semibold text-gray-900">Metagrupos asignados</h2>
                    <p class="text-sm text-gray-500">
                        Accede solo a los metagrupos que lideras para seguir a tus equipos.
                    </p>
                </div>
                <div class="text-sm text-gray-500 px-4">
                    Total: {{ $rows->count() }}
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                    No tienes metagrupos asignados todavía.
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">Metagrupo</th>
                                <th class="px-4 py-3 font-medium">Líder</th>
                                <th class="px-4 py-3 font-medium">Grupos</th>
                                <th class="px-4 py-3 font-medium">Personas</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-4 font-medium text-gray-800">{{ $row->nombre }}</td>
                                    <td class="px-4 py-4 text-gray-600">
                                        {{ $row->lider ? trim($row->lider->apellido . ' ' . $row->lider->nombre) : '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-gray-700">{{ $row->grupos_count ?? $row->grupos()->count() }}</td>
                                    <td class="px-4 py-4 text-gray-700">{{ $row->personas_count ?? 0 }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ $this->getViewUrl($row) }}" class="font-medium text-primary-600 hover:text-primary-500">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
