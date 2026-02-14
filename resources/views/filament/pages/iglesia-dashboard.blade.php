<x-filament-panels::page>
	@foreach (Filament\Facades\Filament::getWidgets() as $widget)
		@livewire($widget)
	@endforeach
</x-filament-panels::page>
