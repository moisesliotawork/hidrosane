<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Paso 1 · Buscar por número de nota
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
                        Buscar nota
                    </x-filament::button>
                </div>
            </div>

            @if ($searchedByNote && $notFoundMessage)
                <div class="mt-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100">
                    {{ $notFoundMessage }}
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Paso 1 · Buscar por teléfono del cliente
            </x-slot>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Introduce el teléfono del cliente para ver todas sus notas y reasignar cualquiera de ellas.
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

            @if ($searchedByPhone && $phoneSearchMessage)
                <div class="mt-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100">
                    {{ $phoneSearchMessage }}
                </div>
            @endif

            @if ($searchedByPhone && $this->phoneNotes->isNotEmpty())
                <div class="mt-4 space-y-3">
                    @if ($matchedCustomersLabel)
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            Cliente(s): {{ $matchedCustomersLabel }}
                        </p>
                    @endif

                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">No. Nota</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Fecha nota</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Fecha asignación</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Fecha visita</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Asignación actual</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Estado</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-950">
                                @foreach ($this->phoneNotes as $phoneNote)
                                    <tr @class([
                                        'bg-primary-50 dark:bg-primary-950' => $foundNoteId === $phoneNote->id,
                                    ])>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                            {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatNroNota($phoneNote->nro_nota) }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ $phoneNote->created_at?->format('d/m/Y H:i') ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ $phoneNote->assignment_date?->format('d/m/Y') ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ $phoneNote->visit_date?->format('d/m/Y H:i') ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @if ($phoneNote->reten)
                                                COMERCIAL RETÉN
                                            @elseif ($phoneNote->comercial)
                                                {{ $phoneNote->comercial->empleado_id }}
                                                {{ trim($phoneNote->comercial->name . ' ' . $phoneNote->comercial->last_name) }}
                                            @else
                                                Sin asignar
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ $phoneNote->estado_terminal?->label() ?: 'S/E' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-filament::button
                                                size="sm"
                                                color="primary"
                                                wire:click="selectNoteForAssignment({{ $phoneNote->id }})"
                                            >
                                                Reasignar
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </x-filament::section>

        @if ($searchedByNote || $searchedByPhone)
            <div class="flex justify-start">
                <x-filament::button color="info" wire:click="clearSearch">
                    Limpiar búsqueda
                </x-filament::button>
            </div>
        @endif

        @if ($this->foundNote)
            @php($note = $this->foundNote)

            <x-filament::section>
                <x-slot name="heading">
                    Paso 2 · Reasignar nota {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatNroNota($note->nro_nota) }}
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
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fechas de la nota</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Creada: {{ $note->created_at?->format('d/m/Y H:i') ?: '—' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Asignación: {{ $note->assignment_date?->format('d/m/Y') ?: '—' }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Visita: {{ $note->visit_date?->format('d/m/Y H:i') ?: '—' }}
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
                            TN: {{ $note->estado_terminal?->label() ?: 'S/E' }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Estado: {{ $note->status?->label() ?: '—' }}
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
