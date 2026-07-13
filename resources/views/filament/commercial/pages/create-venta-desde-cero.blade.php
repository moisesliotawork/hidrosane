<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    @if ($this->shouldShowPuertaFriaLookupModal())
        <x-filament::modal
            id="puerta-fria-customer-lookup"
            width="2xl"
            :close-by-clicking-away="false"
            :close-by-escaping="false"
        >
            <x-slot name="heading">
                Buscar cliente antes de crear Puerta Fría
            </x-slot>

            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Introduce el teléfono y el nombre del cliente para evitar duplicados en el sistema.
                </p>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-950 dark:text-white">Teléfono</label>
                        <input
                            type="tel"
                            wire:model.defer="lookupPhone"
                            placeholder="999 999 999"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-950 dark:text-white">Nombre del cliente</label>
                        <input
                            type="text"
                            wire:model.defer="lookupName"
                            placeholder="Nombre y apellidos"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        />
                    </div>
                </div>

                <div class="flex justify-start">
                    <x-filament::button color="warning" wire:click="searchPuertaFriaCustomers" wire:loading.attr="disabled">
                        Buscar
                    </x-filament::button>
                </div>

                @if ($lookupMessage)
                    <div class="rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
                        {{ $lookupMessage }}
                    </div>
                @endif

                @if (! empty($lookupResults))
                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Clientes encontrados</p>

                        @foreach ($lookupResults as $result)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <input
                                    type="radio"
                                    wire:model="lookupSelectedChoice"
                                    value="{{ $result['id'] }}"
                                    class="mt-1"
                                />
                                <span class="text-sm text-gray-800 dark:text-gray-100">
                                    <span class="font-semibold">{{ $result['name'] }}</span>
                                    @if (! empty($result['dni']))
                                        <span class="block text-gray-500 dark:text-gray-400">DNI: {{ $result['dni'] }}</span>
                                    @endif
                                    @if (! empty($result['phone']))
                                        <span class="block text-gray-500 dark:text-gray-400">Tel: {{ $result['phone'] }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach

                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-primary-300 bg-primary-50 p-3 dark:border-primary-700 dark:bg-primary-950">
                            <input
                                type="radio"
                                wire:model="lookupSelectedChoice"
                                value="__new__"
                                class="mt-1"
                            />
                            <span class="text-sm font-semibold text-primary-800 dark:text-primary-100">
                                Crear cliente nuevo
                            </span>
                        </label>
                    </div>
                @elseif ($lookupSearched)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-primary-300 bg-primary-50 p-3 dark:border-primary-700 dark:bg-primary-950">
                        <input
                            type="radio"
                            wire:model="lookupSelectedChoice"
                            value="__new__"
                            class="mt-1"
                        />
                        <span class="text-sm font-semibold text-primary-800 dark:text-primary-100">
                            Crear cliente nuevo
                        </span>
                    </label>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex w-full items-center justify-between gap-3">
                    <x-filament::button
                        color="gray"
                        wire:click="cancelPuertaFriaLookup"
                    >
                        Cancelar
                    </x-filament::button>

                    <x-filament::button
                        color="success"
                        wire:click="continuePuertaFriaLookup"
                        wire:loading.attr="disabled"
                    >
                        Continuar
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    @endif

    @if ($this->isPuertaFriaLookupBlockingForm())
        <div class="rounded-xl border border-warning-300 bg-warning-50 px-6 py-8 text-center dark:border-warning-700 dark:bg-warning-950">
            <p class="text-base font-semibold text-warning-900 dark:text-warning-100">
                Búsqueda de cliente obligatoria
            </p>
            <p class="mt-2 text-sm text-warning-800 dark:text-warning-200">
                Para crear un contrato de Puerta Fría debes buscar el cliente primero.
                Puedes cancelar el modal, pero no podrás avanzar hasta completar la búsqueda.
            </p>

            <div class="mt-5">
                <x-filament::button color="warning" wire:click="openPuertaFriaLookupModal">
                    Buscar cliente
                </x-filament::button>
            </div>
        </div>
    @else
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="create"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
    @endif

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>

@script
<script>
    $wire.on('open-puerta-fria-lookup-modal', () => {
        $dispatch('open-modal', { id: 'puerta-fria-customer-lookup' });
    });

    $wire.on('close-puerta-fria-lookup-modal', () => {
        $dispatch('close-modal', { id: 'puerta-fria-customer-lookup' });
    });
</script>
@endscript
