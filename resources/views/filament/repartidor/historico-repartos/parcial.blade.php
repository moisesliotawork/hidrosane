<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Observación capturada en el modal previo (opcional) --}}
        @php $obs = session("reparto_parcial_obs_{$this->record->id}"); @endphp
        @if($obs)
            <div class="p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/30">
                <strong>Observación:</strong>
                <div class="mt-1">{{ $obs }}</div>
            </div>
        @endif

        {{-- SOLO la sección de ofertas con Cant. entregada editable --}}
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button wire:click="save">
                Guardar cambios
            </x-filament::button>

            <x-filament::button tag="a"
                href="{{ \App\Filament\Repartidor\Resources\HistoricoRepartosResource::getUrl('index', panel: 'repartidor') }}"
                color="gray" wire:navigate>
                Volver
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>