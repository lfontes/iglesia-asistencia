<x-filament-panels::page>
    @php
        $summary = $this->getConversationSummary();
        $messages = $this->getConversationMessages();
        $statusColors = [
            'accepted' => 'info',
            'sent' => 'info',
            'delivered' => 'success',
            'read' => 'success',
            'failed' => 'danger',
            'failed_request' => 'danger',
            'received' => 'warning',
            'unknown' => 'gray',
        ];
    @endphp

    @if (! $summary)
        <x-filament::section
            icon="heroicon-o-chat-bubble-bottom-center-text"
            icon-color="gray"
            heading="Conversación no encontrada"
        >
            <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-300">
                No se encontró la conversación.
            </div>
        </x-filament::section>
    @else
        <div class="grid gap-6">
            <x-filament::section
                icon="heroicon-o-identification"
                icon-color="success"
                heading="{{ $summary['persona'] ? trim($summary['persona']->apellido . ' ' . $summary['persona']->nombre) : $summary['telefono'] }}"
                description="Detalle de la conversación seleccionada."
            >
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Teléfono</div>
                        <div class="mt-1 text-sm text-gray-900 dark:text-white">{{ $summary['telefono'] }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Grupo</div>
                        <div class="mt-1 text-sm text-gray-900 dark:text-white">{{ $summary['grupo']?->nombre ?? 'Sin grupo asociado' }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado</div>
                        <div class="mt-2">
                            <x-filament::badge :color="$summary['ventana_abierta'] ? 'success' : 'gray'">
                                {{ $summary['ventana_abierta'] ? 'Ventana de 24 horas abierta' : 'Ventana cerrada: usa plantilla' }}
                            </x-filament::badge>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section
                icon="heroicon-o-chat-bubble-left-right"
                icon-color="primary"
                heading="Historial"
                description="Mensajes ordenados cronológicamente."
            >
                <div class="grid gap-4">
                    @foreach ($messages as $message)
                        <div class="flex {{ $message->isOutbound() ? 'justify-end' : 'justify-start' }}">
                            <div class="w-full max-w-3xl rounded-2xl border px-4 py-3 shadow-sm {{ $message->isOutbound() ? 'border-primary-200 bg-primary-50 dark:border-primary-500/30 dark:bg-primary-500/10' : 'border-gray-200 bg-white dark:border-white/10 dark:bg-white/5' }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ $message->isOutbound() ? 'Enviado' : 'Recibido' }}
                                    </span>

                                    <x-filament::badge :color="$statusColors[$message->status] ?? $statusColors['unknown']">
                                        {{ $message->status }}
                                    </x-filament::badge>

                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $message->created_at?->format('d/m/Y H:i') }}
                                    </span>
                                </div>

                                <div class="mt-3 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-100">
                                    {{ $message->body ?: 'Sin contenido visible' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section
                icon="heroicon-o-paper-airplane"
                icon-color="success"
                heading="Responder"
                description="Envía un mensaje libre solo mientras la ventana esté abierta."
            >
                {{ $this->form }}

                @if (! $summary['ventana_abierta'])
                    <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                        La persona no escribió en las últimas 24 horas. Para reanudar la conversación necesitarás una plantilla aprobada.
                    </p>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
