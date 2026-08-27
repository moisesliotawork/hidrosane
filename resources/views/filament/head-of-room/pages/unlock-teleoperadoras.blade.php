<x-filament-panels::page>
    <div class="mx-auto w-full max-w-md">
        <form wire:submit="unlock" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary" class="w-full">
                Entrar
            </x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
