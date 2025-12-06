<x-filament-panels::page>

    <x-filament::modal id="modal-transfer" width="md">
        <x-slot name="heading">
            Solicitud de transferencia
        </x-slot>

        <div class="space-y-2 text-sm">
            <p><strong>De:</strong> {{ $transfer->fromComercial->name }}</p>
            <p><strong>Para:</strong> {{ $transfer->toComercial->name }}</p>
            <p><strong>Cantidad:</strong> {{ $transfer->amount }} crema(s)</p>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button color="danger" wire:click="reject">
                    Rechazar
                </x-filament::button>

                <x-filament::button color="success" wire:click="accept">
                    Aceptar
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

</x-filament-panels::page>