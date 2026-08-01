<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <strong>Solo SuperAdmin · recuperación.</strong>
            Este módulo no altera altas comerciales, puerta fría ni repartos.
            Flujo: subir docs → revisar datos → <strong>Aceptar</strong> (tabla) → <strong>Agregar Contrato</strong> (crea la venta al cliente por DNI).
        </div>

        @if ($step === 'upload')
            <form wire:submit.prevent="analyzeDocuments" class="space-y-4">
                {{ $this->uploadForm }}
                <x-filament::button type="submit" color="warning" wire:loading.attr="disabled">
                    Analizar documentos
                </x-filament::button>
            </form>
        @else
            <form wire:submit.prevent="acceptRecovered" class="space-y-4">
                {{ $this->reviewForm }}
                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="submit" color="success">
                        Aceptar (guardar en tabla)
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="cancelReview">
                        Cancelar revisión
                    </x-filament::button>
                </div>
            </form>
        @endif

        <div class="pt-2">
            <h2 class="mb-3 text-base font-bold tracking-wide text-gray-900 dark:text-gray-100">
                RECUPERADOS ACEPTADOS
            </h2>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
