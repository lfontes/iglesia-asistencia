<x-filament-panels::page>
    @php
        $messages = $this->getMessages();
        $statusColors = [
            'accepted' => 'bg-blue-100 text-blue-800',
            'sent' => 'bg-sky-100 text-sky-800',
            'delivered' => 'bg-emerald-100 text-emerald-800',
            'read' => 'bg-green-100 text-green-800',
            'failed' => 'bg-rose-100 text-rose-800',
            'failed_request' => 'bg-rose-100 text-rose-800',
            'unknown' => 'bg-gray-100 text-gray-800',
        ];
    @endphp

    <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Historial de mensajes</h2>
                <p class="text-sm text-gray-500">Estados recibidos desde Meta para pruebas y envíos manuales.</p>
            </div>
        </div>

        @if ($messages->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                Aún no hay mensajes registrados.
            </div>
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3 font-medium">Uso</th>
                            <th class="px-4 py-3 font-medium">Contexto</th>
                            <th class="px-4 py-3 font-medium">Destino</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 font-medium">Mensaje</th>
                            <th class="px-4 py-3 font-medium">Meta ID</th>
                            <th class="px-4 py-3 font-medium">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($messages as $message)
                            <tr class="align-top">
                                <td class="px-4 py-4 text-gray-700">
                                    {{ $message->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-4 text-gray-700">
                                    {{ $message->use_case ?: 'Sin uso' }}
                                </td>
                                <td class="px-4 py-4 text-gray-700">
                                    @if ($message->grupo)
                                        <div>{{ $message->grupo->nombre }}</div>
                                    @endif
                                    @if ($message->persona)
                                        <div class="text-xs text-gray-500">
                                            {{ trim(($message->persona->apellido ?? '') . ' ' . ($message->persona->nombre ?? '')) }}
                                        </div>
                                    @endif
                                    @if ($message->periodo_inicio && $message->periodo_fin)
                                        <div class="text-xs text-gray-500">
                                            {{ $message->periodo_inicio->format('d/m/Y') }} - {{ $message->periodo_fin->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-gray-700">
                                    <div>{{ $message->to_phone ?: 'Sin número' }}</div>
                                    @if ($message->recipient_wa_id)
                                        <div class="text-xs text-gray-500">wa_id: {{ $message->recipient_wa_id }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$message->status] ?? $statusColors['unknown'] }}">
                                        {{ $message->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-gray-700">
                                    <div class="max-w-md whitespace-pre-wrap">{{ $message->body }}</div>
                                </td>
                                <td class="px-4 py-4 text-xs text-gray-500">
                                    {{ $message->provider_message_id ?: 'Sin ID' }}
                                </td>
                                <td class="px-4 py-4 text-gray-700">
                                    {{ $message->error_message ?: 'Sin novedades' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
