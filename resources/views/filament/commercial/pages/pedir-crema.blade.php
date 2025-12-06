<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Título y subtítulo, sin colores fijos --}}
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Pedir crema a otro comercial
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Nota N.º {{ $this->record->nro_nota }}
                — Cliente: {{ $this->record->customer->first_names }}
                {{ $this->record->customer->last_names }}
            </p>
        </div>

        {{-- Usa el SECTION de Filament, que ya respeta el tema (claro/oscuro) --}}
        <x-filament::section>
            <x-slot name="heading">
                Datos de la solicitud
            </x-slot>

            <x-filament-panels::form wire:submit="submit">
                {{ $this->form }}

                <x-filament-panels::form.actions :actions="$this->getFormActions()" />
            </x-filament-panels::form>
        </x-filament::section>
    </div>
</x-filament-panels::page>