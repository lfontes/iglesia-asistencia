<x-filament-panels::page>
    @php($preview = $this->getAudiencePreview())
    @php($rows = $preview['rows'])
    @php($stats = $preview['stats'])

    <div class="space-y-6">
        <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
            <x-slot name="heading">
                Construir audiencia
            </x-slot>

            <x-slot name="description">
                Combina asistentes de eventos y miembros de grupos para armar una sola audiencia.
            </x-slot>

            <form wire:submit.prevent="enviarInvitaciones" class="space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="submit">
                        Enviar invitaciones
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @livewire(
            \App\Filament\Widgets\InvitacionesResumenWidget::class,
            [
                'eventoFechaIdsOrigen' => $this->evento_fecha_ids_origen,
                'grupoIdsOrigen' => $this->grupo_ids_origen,
                'eventoFechaIdDestino' => $this->evento_fecha_id_destino,
                'excluirSinTelefono' => $this->excluir_sin_telefono,
                'excluirYaAsistieronDestino' => $this->excluir_ya_asistieron_destino,
                'excluirYaInvitadosDestino' => $this->excluir_ya_invitados_destino,
            ],
            key('invitaciones-resumen-' . md5(json_encode([
                $this->evento_fecha_ids_origen,
                $this->grupo_ids_origen,
                $this->evento_fecha_id_destino,
                $this->excluir_sin_telefono,
                $this->excluir_ya_asistieron_destino,
                $this->excluir_ya_invitados_destino,
            ])))
        )

        <x-filament::section icon="heroicon-o-eye" icon-color="primary">
            <x-slot name="heading">
                Vista previa de audiencia
            </x-slot>

            @if ($rows->isEmpty())
                <x-filament-tables::empty-state
                    heading="Sin audiencia para mostrar"
                    description="Selecciona eventos o grupos origen para ver la audiencia."
                    icon="heroicon-o-user-group"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-stone-500 dark:text-gray-300">
                                <th class="px-4 py-3 font-medium">Persona</th>
                                <th class="px-4 py-3 font-medium">Telefono</th>
                                <th class="px-4 py-3 font-medium">Fuentes</th>
                                <th class="px-4 py-3 font-medium">Ya asistio</th>
                                <th class="px-4 py-3 font-medium">Ya invitado</th>
                                <th class="px-4 py-3 font-medium">Elegible</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 dark:divide-white/10">
                            @foreach ($rows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-4 text-stone-800 dark:text-white">{{ $row['nombre'] }}</td>
                                    <td class="px-4 py-4 text-stone-600 dark:text-gray-300">{{ $row['telefono'] ?: '-' }}</td>
                                    <td class="px-4 py-4 text-stone-700 dark:text-gray-200">
                                        <div class="space-y-1">
                                            @foreach ($row['sources'] as $source)
                                                <div>{{ $source }}</div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-filament::badge :color="$row['already_attended_destination'] ? 'info' : 'gray'">
                                            {{ $row['already_attended_destination'] ? 'Si' : 'No' }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-filament::badge :color="$row['already_invited_destination'] ? 'warning' : 'gray'">
                                            {{ $row['already_invited_destination'] ? 'Si' : 'No' }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-filament::badge :color="$row['eligible'] ? 'success' : 'warning'">
                                            {{ $row['eligible'] ? 'Si' : 'No' }}
                                        </x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
