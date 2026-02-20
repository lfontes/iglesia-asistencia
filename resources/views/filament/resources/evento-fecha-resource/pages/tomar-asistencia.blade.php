<x-filament-panels::page>
<x-filament::page>
    <form wire:submit.prevent="guardar">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Guardar Asistencia
        </x-filament::button>
    </form>
</x-filament::page>

</x-filament-panels::page>
