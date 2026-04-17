<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción al evento</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[radial-gradient(circle_at_top,_#fef3c7,_#f5f5f4_42%,_#fafaf9_100%)] text-stone-900 dark:bg-[radial-gradient(circle_at_top,_#1f2937,_#111827_42%,_#020617_100%)] dark:text-white">
    <div class="mx-auto max-w-4xl px-4 py-12">
        <div class="overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/80">
            <div class="border-b border-stone-200 bg-stone-50 px-8 py-8 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Inscripción</p>
                <h1 class="mt-2 text-3xl font-semibold text-stone-950 dark:text-white">{{ $eventoFecha->evento->nombre }}</h1>
                <div class="mt-4 flex flex-wrap gap-4 text-sm text-stone-600 dark:text-gray-300">
                    <span class="rounded-full bg-white px-4 py-2 ring-1 ring-stone-200 dark:bg-white/10 dark:ring-white/10">
                        Fecha: {{ \Carbon\Carbon::parse($eventoFecha->fecha)->format('d/m/Y') }}
                    </span>
                    @if (filled($eventoFecha->evento->descripcion))
                        <span class="rounded-full bg-white px-4 py-2 ring-1 ring-stone-200 dark:bg-white/10 dark:ring-white/10">
                            {{ $eventoFecha->evento->descripcion }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="px-8 py-8">
                @if (session('success'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="mb-8 rounded-2xl border border-stone-200 bg-stone-50 px-5 py-4 text-sm text-stone-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                    Completa tus datos para inscribirte. Si encontramos una persona parecida en la base, te la vamos a mostrar para que confirmes si eres tú.
                </div>

                <form method="POST" action="{{ route('eventos.inscripcion.store', $eventoFecha) }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-stone-700 dark:text-gray-200">Nombre</span>
                            <input
                                name="nombre"
                                value="{{ old('nombre', $input['nombre'] ?? '') }}"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                required
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-stone-700 dark:text-gray-200">Apellido</span>
                            <input
                                name="apellido"
                                value="{{ old('apellido', $input['apellido'] ?? '') }}"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                required
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-stone-700 dark:text-gray-200">Fecha de nacimiento</span>
                            <input
                                type="date"
                                name="fecha_nacimiento"
                                value="{{ old('fecha_nacimiento', $input['fecha_nacimiento'] ?? '') }}"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-stone-700 dark:text-gray-200">Teléfono</span>
                            <input
                                name="telefono"
                                value="{{ old('telefono', $input['telefono'] ?? '') }}"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                        </label>

                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-sm font-medium text-stone-700 dark:text-gray-200">Email</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', $input['email'] ?? '') }}"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                        </label>

                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-sm font-medium text-stone-700 dark:text-gray-200">Departamento</span>
                            <select
                                name="departamento"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                                <option value="">Selecciona un departamento</option>
                                @foreach ($departamentos as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('departamento', $input['departamento'] ?? '') === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button class="rounded-full bg-stone-950 px-6 py-3 text-sm font-medium text-white dark:bg-white dark:text-slate-900">
                            Continuar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($candidates->isNotEmpty())
        <div id="candidate-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 px-4 py-8">
            <div class="max-h-full w-full max-w-4xl overflow-y-auto rounded-[2rem] bg-white p-8 shadow-2xl dark:bg-slate-900">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Posibles coincidencias</p>
                <h2 class="mt-2 text-2xl font-semibold text-stone-950 dark:text-white">Encontramos personas parecidas</h2>
                <p class="mt-3 text-sm text-stone-600 dark:text-gray-300">
                    Revisa estas coincidencias antes de crear una persona nueva. Si eres una de ellas, actualizamos sus datos y registramos la inscripción para este evento.
                </p>

                <div class="mt-6 grid gap-4">
                    @foreach ($candidates as $candidate)
                        <div class="rounded-3xl border border-stone-200 bg-stone-50 p-5 dark:border-white/10 dark:bg-white/5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="text-lg font-semibold text-stone-950 dark:text-white">
                                        {{ trim($candidate->nombre . ' ' . $candidate->apellido) }}
                                    </div>
                                    <div class="mt-2 grid gap-2 text-sm text-stone-600 dark:text-gray-300 md:grid-cols-2">
                                        <div><strong>Fecha de nacimiento:</strong> {{ $candidate->fecha_nacimiento ?: '-' }}</div>
                                        <div><strong>Teléfono:</strong> {{ $candidate->telefono ?: '-' }}</div>
                                        <div><strong>Email:</strong> {{ $candidate->email ?: '-' }}</div>
                                        <div><strong>Departamento:</strong> {{ $candidate->departamento ?: '-' }}</div>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('eventos.inscripcion.store', $eventoFecha) }}">
                                    @csrf
                                    @foreach ($input as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? $value : '' }}">
                                    @endforeach
                                    <input type="hidden" name="modo" value="confirmar_existente">
                                    <input type="hidden" name="persona_existente_id" value="{{ $candidate->id }}">
                                    <button class="rounded-full bg-emerald-700 px-5 py-3 text-sm font-medium text-white dark:bg-emerald-600">
                                        Sí, soy esta persona
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('eventos.inscripcion.store', $eventoFecha) }}">
                        @csrf
                        @foreach ($input as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? $value : '' }}">
                        @endforeach
                        <input type="hidden" name="modo" value="crear_nueva">
                        <button class="rounded-full border border-stone-300 px-5 py-3 text-sm font-medium text-stone-800 dark:border-white/15 dark:text-white">
                            No soy ninguna, crear persona nueva
                        </button>
                    </form>

                    <button
                        type="button"
                        onclick="document.getElementById('candidate-modal')?.remove()"
                        class="rounded-full border border-transparent px-5 py-3 text-sm font-medium text-stone-500 dark:text-gray-300"
                    >
                        Volver y revisar mis datos
                    </button>
                </div>
            </div>
        </div>
    @endif
</body>
</html>
