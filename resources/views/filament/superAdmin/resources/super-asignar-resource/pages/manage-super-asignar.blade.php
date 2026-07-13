<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Paso 1 · Buscar nota
            </x-slot>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Introduce el número de nota para localizarla y reasignarla sin restricciones.
            </p>

            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="flex-1">
                    <label class="text-sm font-medium text-gray-950 dark:text-white">No. NOTA</label>
                    <input
                        type="text"
                        wire:model.defer="searchNroNota"
                        placeholder="Ej. 04204"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="warning" wire:click="searchNote" wire:loading.attr="disabled">
                        Buscar
                    </x-filament::button>

                    @if ($searched)
                        <x-filament::button color="info" wire:click="clearSearch">
                            Limpiar búsqueda
                        </x-filament::button>
                    @endif
                </div>
            </div>

            @if ($notFoundMessage)
                <div class="mt-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100">
                    {{ $notFoundMessage }}
                </div>
            @endif
        </x-filament::section>

        @if ($this->foundNote)
            @php($note = $this->foundNote)

            <x-filament::section>
                <x-slot name="heading">
                    Paso 2 · Reasignar nota {{ strlen($note->nro_nota) === 5 ? substr($note->nro_nota, 0, 3) . ' ' . substr($note->nro_nota, 3, 2) : $note->nro_nota }}
                </x-slot>

                <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cliente</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ strtoupper(trim(($note->customer?->first_names ?? '') . ' ' . ($note->customer?->last_names ?? ''))) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Tel: {{ $note->customer?->phone1_commercial ?: ($note->customer?->phone ?: '—') }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            CP: {{ $note->customer?->postal_code ?: '—' }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Asignación actual</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            @if ($note->reten)
                                COMERCIAL RETÉN
                            @elseif ($note->comercial)
                                {{ $note->comercial->empleado_id }}
                                {{ trim($note->comercial->name . ' ' . $note->comercial->last_name) }}
                            @else
                                Sin asignar
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Fecha: {{ $note->assignment_date?->format('d/m/Y') ?: '—' }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            TN: {{ $note->estado_terminal?->label() ?: 'S/E' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Estado: {{ $note->status?->label() ?: '—' }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            T. Op.: {{ $note->user?->empleado_id ?: '—' }}
                        </p>
                    </div>
                </div>

                <form wire:submit="assignNote" class="space-y-4">
                    {{ $this->form }}

                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="submit" color="success">
                            Reasignar nota
                        </x-filament::button>

                        <x-filament::button type="button" color="gray" wire:click="clearSearch">
                            Nueva búsqueda
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
