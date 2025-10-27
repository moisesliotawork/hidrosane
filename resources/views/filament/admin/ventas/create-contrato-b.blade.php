<x-filament-panels::page>
    <form wire:submit.prevent="create" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3">
            <x-filament::button type="submit" icon="heroicon-o-document-plus" color="success" size="lg">
                Crear Contrato -B
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>