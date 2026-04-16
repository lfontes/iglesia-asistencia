<x-filament-panels::page>
    @php($preview = $this->getAudiencePreview())
    @php($rows = $preview['rows'])
    @php($stats = $preview['stats'])

    <div class="space-y-6">
        <div class="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-stone-900">Construir audiencia</h2>
                <p class="text-sm text-stone-500">
                    Combina asistentes de eventos y miembros de grupos para armar una sola audiencia y enviar invitaciones al evento destino.
                </p>
            </div>

            <form wire:submit.prevent="enviarInvitaciones" class="space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="submit">
                        Enviar invitaciones
                    </x-filament::button>
                </div>
            </form>
        </div>

        <div class="grid gap-4 md:grid-cols-5">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-stone-500">Personas unicas</div>
                <div class="mt-2 text-2xl font-semibold text-stone-900">{{ $stats['total_unicos'] }}</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <div class="text-sm text-amber-700">Sin telefono</div>
                <div class="mt-2 text-2xl font-semibold text-amber-900">{{ $stats['sin_telefono'] }}</div>
            </div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                <div class="text-sm text-sky-700">Ya asistieron</div>
                <div class="mt-2 text-2xl font-semibold text-sky-900">{{ $stats['ya_asistieron_destino'] }}</div>
            </div>
            <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm">
                <div class="text-sm text-violet-700">Ya invitados</div>
                <div class="mt-2 text-2xl font-semibold text-violet-900">{{ $stats['ya_invitados'] }}</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <div class="text-sm text-emerald-700">Destinatarios finales</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ $stats['finales'] }}</div>
            </div>
        </div>

        <div class="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-stone-900">Vista previa de audiencia</h2>
                    <p class="text-sm text-stone-500">
                        Puedes revisar de dónde entra cada persona y si queda elegible para la invitación final.
                    </p>
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-sm text-stone-500">
                    Selecciona eventos o grupos origen para ver la audiencia.
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-stone-500">
                                <th class="px-4 py-3 font-medium">Persona</th>
                                <th class="px-4 py-3 font-medium">Telefono</th>
                                <th class="px-4 py-3 font-medium">Fuentes</th>
                                <th class="px-4 py-3 font-medium">Ya asistio</th>
                                <th class="px-4 py-3 font-medium">Ya invitado</th>
                                <th class="px-4 py-3 font-medium">Elegible</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($rows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-4 text-stone-800">{{ $row['nombre'] }}</td>
                                    <td class="px-4 py-4 text-stone-600">{{ $row['telefono'] ?: '-' }}</td>
                                    <td class="px-4 py-4 text-stone-700">
                                        <div class="space-y-1">
                                            @foreach ($row['sources'] as $source)
                                                <div>{{ $source }}</div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-medium',
                                            'bg-sky-100 text-sky-800' => $row['already_attended_destination'],
                                            'bg-stone-100 text-stone-700' => ! $row['already_attended_destination'],
                                        ])>
                                            {{ $row['already_attended_destination'] ? 'Si' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-medium',
                                            'bg-violet-100 text-violet-800' => $row['already_invited_destination'],
                                            'bg-stone-100 text-stone-700' => ! $row['already_invited_destination'],
                                        ])>
                                            {{ $row['already_invited_destination'] ? 'Si' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800' => $row['eligible'],
                                            'bg-amber-100 text-amber-800' => ! $row['eligible'],
                                        ])>
                                            {{ $row['eligible'] ? 'Si' : 'No' }}
                                        </span>
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
