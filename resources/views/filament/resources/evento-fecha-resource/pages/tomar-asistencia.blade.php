<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section compact>
            <div class="text-sm text-gray-500 dark:text-gray-300">Inscriptos</div>
            <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->getTotalInscriptos() }}</div>
        </x-filament::section>

        <x-filament::section compact>
            <div class="text-sm text-gray-500 dark:text-gray-300">Presentes</div>
            <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->getTotalPresentes() }}</div>
        </x-filament::section>

        <x-filament::section compact>
            <div class="text-sm text-success-600 dark:text-success-400">Inscriptos presentes</div>
            <div class="mt-2 text-2xl font-semibold text-success-700 dark:text-success-300">{{ $this->getTotalInscriptosPresentes() }}</div>
        </x-filament::section>

        <x-filament::section compact>
            <div class="text-sm text-warning-600 dark:text-warning-400">Presentes sin inscripción</div>
            <div class="mt-2 text-2xl font-semibold text-warning-700 dark:text-warning-300">{{ $this->getTotalPresentesNoInscriptos() }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        {{ $this->table }}
    </x-filament::section>

    <x-filament::section
        heading="Asistencia manual"
        description="Busca personas fuera del listado de inscriptos y márquelas como presentes para esta fecha."
    >
        <form wire:submit.prevent="guardar" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit">
                Guardar asistencia
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
