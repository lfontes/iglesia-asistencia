<x-filament-panels::page>
    @php
        $conversations = $this->getConversations();
    @endphp

    <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Conversaciones</h2>
                <p class="text-sm text-gray-500">Mensajes entrantes y salientes agrupados por contacto.</p>
            </div>
        </div>

        @if ($conversations->isEmpty())
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                Aún no hay conversaciones registradas.
            </div>
        @else
            <div class="mt-6 space-y-3">
                @foreach ($conversations as $conversation)
                    <a
                        href="{{ \App\Filament\Pages\WhatsAppConversacion::getUrl(['key' => $conversation['conversation_key']]) }}"
                        class="block rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:bg-primary-50/20"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="truncate text-sm font-semibold text-gray-900">
                                        {{ $conversation['persona'] ? trim($conversation['persona']->apellido . ' ' . $conversation['persona']->nombre) : $conversation['telefono'] }}
                                    </h3>
                                    @if ($conversation['no_leidos'] > 0)
                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                            {{ $conversation['no_leidos'] }} sin leer
                                        </span>
                                    @endif
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $conversation['ventana_abierta'] ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $conversation['ventana_abierta'] ? 'Ventana abierta' : 'Requiere plantilla' }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $conversation['telefono'] }}
                                    @if ($conversation['grupo'])
                                        · {{ $conversation['grupo']->nombre }}
                                    @endif
                                </div>
                                <p class="mt-2 truncate text-sm text-gray-700">
                                    {{ $conversation['ultimo_texto'] ?: 'Sin contenido de texto' }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right text-xs text-gray-500">
                                {{ $conversation['ultimo_momento']?->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
