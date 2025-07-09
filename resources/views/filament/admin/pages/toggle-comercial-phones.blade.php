<x-filament::page>
    {{ $this->form }}

    <x-filament::button wire:click="submit" class="mt-4" icon="heroicon-o-check">
        Actualizar
    </x-filament::button>
</x-filament::page>