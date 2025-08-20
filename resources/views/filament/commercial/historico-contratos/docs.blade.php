<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button wire:click="save">
                Guardar documentos
            </x-filament::button>

            <x-filament::button tag="a"
                href="{{ \App\Filament\Commercial\Resources\HistoricoContratosResource::getUrl('index', panel: 'comercial') }}"
                color="gray" wire:navigate>
                Volver
            </x-filament::button>

        </div>
    </div>
</x-filament-panels::page>