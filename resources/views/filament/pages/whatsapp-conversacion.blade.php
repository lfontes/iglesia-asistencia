<x-filament-panels::page>
    @php
        $summary = $this->getConversationSummary();
        $messages = $this->getConversationMessages();
        $statusColors = [
            'accepted' => 'bg-blue-100 text-blue-800',
            'sent' => 'bg-sky-100 text-sky-800',
            'delivered' => 'bg-emerald-100 text-emerald-800',
            'read' => 'bg-green-100 text-green-800',
            'failed' => 'bg-rose-100 text-rose-800',
            'failed_request' => 'bg-rose-100 text-rose-800',
            'received' => 'bg-amber-100 text-amber-800',
            'unknown' => 'bg-gray-100 text-gray-800',
        ];
    @endphp

    <div class="space-y-6">
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            @if (! $summary)
                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                    No se encontró la conversación.
                </div>
            @else
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $summary['persona'] ? trim($summary['persona']->apellido . ' ' . $summary['persona']->nombre) : $summary['telefono'] }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            {{ $summary['telefono'] }}
                            @if ($summary['grupo'])
                                · {{ $summary['grupo']->nombre }}
                            @endif
                        </p>
                    </div>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $summary['ventana_abierta'] ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $summary['ventana_abierta'] ? 'Ventana de 24 horas abierta' : 'Ventana cerrada: usa plantilla' }}
                    </span>
                </div>

                <div class="mt-6 space-y-3">
                    @foreach ($messages as $message)
                        <div class="flex {{ $message->isOutbound() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-2xl rounded-2xl px-4 py-3 {{ $message->isOutbound() ? 'bg-primary-100 text-primary-900' : 'bg-gray-100 text-gray-900' }}">
                                <div class="whitespace-pre-wrap text-sm">{{ $message->body ?: 'Sin contenido visible' }}</div>
                                <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                                    <span>{{ $message->created_at?->format('d/m/Y H:i') }}</span>
                                    <span>·</span>
                                    <span>{{ $message->isOutbound() ? 'Enviado' : 'Recibido' }}</span>
                                    <span class="inline-flex rounded-full px-2 py-0.5 {{ $statusColors[$message->status] ?? $statusColors['unknown'] }}">
                                        {{ $message->status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    {{ $this->form }}
                    @if (! $summary['ventana_abierta'])
                        <p class="mt-3 text-xs text-amber-700">
                            La persona no escribió en las últimas 24 horas. Para reanudar la conversación necesitarás una plantilla aprobada.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
