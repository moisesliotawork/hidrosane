<x-filament-panels::page>
    <div class="space-y-6">
        @if ($searchedByNote || $searchedByPhone || $searchedByCustomerName || count($selectedNoteIds) > 0)
            <div class="flex justify-start">
                <x-filament::button color="info" wire:click="clearSearch">
                    Limpiar búsqueda
                </x-filament::button>
            </div>
        @endif

        @include('filament.superAdmin.resources.super-asignar-resource.partials.selected-notes-basket', [
            'selectedNoteIds' => $selectedNoteIds,
        ])

        <x-filament::section>
            <x-slot name="heading">
                Paso 1 · Buscar por número de nota
            </x-slot>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Busca hasta {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::MAX_SELECTED_NOTES }} notas.
                Puedes introducir una o varias separadas por espacio, coma o punto y coma.
                Cada búsqueda las agrega a la selección para reasignarlas juntas.
            </p>

            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="flex-1">
                    <label class="text-sm font-medium text-gray-950 dark:text-white">No. NOTA</label>
                    <input
                        type="text"
                        wire:model.defer="searchNroNota"
                        placeholder="Ej. 04204 o 04204 04205 04206"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="warning" wire:click="searchNote" wire:loading.attr="disabled">
                        Buscar y agregar
                    </x-filament::button>
                </div>
            </div>

            @if ($noteSearchFeedback)
                <div class="mt-4 rounded-lg border border-primary-300 bg-primary-50 px-4 py-3 text-sm text-primary-900 dark:border-primary-700 dark:bg-primary-950 dark:text-primary-100">
                    {{ $noteSearchFeedback }}
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Paso 1 · Buscar por teléfono del cliente
            </x-slot>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Introduce el teléfono del cliente para ver sus notas y marcarlas en la selección (máximo {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::MAX_SELECTED_NOTES }}).
            </p>

            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="flex-1">
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Teléfono</label>
                    <input
                        type="tel"
                        wire:model.defer="searchPhone"
                        placeholder="999 999 999"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="warning" wire:click="searchNotesByPhone" wire:loading.attr="disabled">
                        Buscar por teléfono
                    </x-filament::button>
                </div>
            </div>

            @if ($searchedByPhone)
                @include('filament.superAdmin.resources.super-asignar-resource.partials.notes-results-block')
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Paso 1 · Buscar por nombre del cliente
            </x-slot>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Introduce el nombre del cliente para ver sus notas y marcarlas en la selección (máximo {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::MAX_SELECTED_NOTES }}).
            </p>

            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="flex-1">
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Nombre del cliente</label>
                    <input
                        type="text"
                        wire:model.defer="searchCustomerName"
                        placeholder="Nombre y apellidos"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="warning" wire:click="searchNotesByCustomerName" wire:loading.attr="disabled">
                        Buscar por nombre
                    </x-filament::button>
                </div>
            </div>

            @if ($searchedByCustomerName)
                @include('filament.superAdmin.resources.super-asignar-resource.partials.notes-results-block')
            @endif
        </x-filament::section>

        @if ($searchedByNote || $searchedByPhone || $searchedByCustomerName || count($selectedNoteIds) > 0)
            <div class="flex justify-start">
                <x-filament::button color="info" wire:click="clearSearch">
                    Limpiar búsqueda
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
