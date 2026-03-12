<x-filament-panels::page>
    <form wire:submit.prevent="guardar">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Guardar Participacion
        </x-filament::button>
    </form>
</x-filament-panels::page>
