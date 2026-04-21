<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Niños IPN</p>
            <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ $stats['ninos'] }}</p>
        </div>
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Aulas activas</p>
            <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ $stats['aulas'] }}</p>
        </div>
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Presentes última fecha</p>
            <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-300">{{ $stats['presentes_ultima_fecha'] }}</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $stats['ultima_fecha'] ? \Illuminate\Support\Carbon::parse($stats['ultima_fecha'])->format('d/m/Y') : 'Sin registros' }}</p>
        </div>
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Promedio 30 días</p>
            <p class="mt-3 text-3xl font-semibold text-primary-600">{{ $stats['promedio_30_dias'] }}%</p>
        </div>
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-500 dark:text-gray-300">Niños sin aula</p>
            <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-300">{{ $stats['sin_aula'] }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <a href="{{ $this->getTomarAsistenciaUrl() }}" class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">Tomar asistencia IPN</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Selecciona un aula, marca presentes y guarda la asistencia de la reunión.</p>
        </a>
        <a href="{{ $this->getReporteUrl() }}" class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">Ver reporte IPN</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Consulta el resumen de asistencia por aula, niño y fecha.</p>
        </a>
    </div>
</x-filament-panels::page>
