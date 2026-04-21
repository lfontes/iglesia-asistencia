<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        <a href="{{ $this->getTomarAsistenciaUrl() }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">Tomar asistencia IPN</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Selecciona un aula, marca presentes y guarda la asistencia de la reunión.</p>
        </a>
        <a href="{{ $this->getReporteUrl() }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">Ver reporte IPN</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Consulta el resumen de asistencia por aula, niño y fecha.</p>
        </a>
    </div>
</x-filament-panels::page>
