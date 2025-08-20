<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Políticas y Comisiones en Rigor</x-slot>
            <x-slot name="description">
                Sube o reemplaza el PDF oficial de políticas y comisiones.
            </x-slot>

            {{ $this->form }}

            <div class="mt-4 flex items-center gap-3">
                <x-filament::button wire:click="save" icon="heroicon-o-check-circle">
                    Guardar
                </x-filament::button>

                @if ($url = $this->getCurrentPublicUrl())
                    <x-filament::button tag="a" href="{{ $url }}" target="_blank" color="gray"
                        icon="heroicon-o-arrow-down-tray">
                        Ver / Descargar PDF actual
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>