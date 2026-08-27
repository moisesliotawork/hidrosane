@if (count($selectedNoteIds) > 0)
    <x-filament::section>
        <x-slot name="heading">
            Notas seleccionadas ({{ count($selectedNoteIds) }}/{{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::MAX_SELECTED_NOTES }})
        </x-slot>

        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
            Estas notas permanecen seleccionadas hasta que confirmes la reasignación masiva o vacíes la selección.
        </p>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">No. Nota</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Cliente</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Teléfono</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Asignación actual</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-950">
                    @foreach ($this->selectedNotes as $selectedNote)
                        <tr wire:key="selected-note-{{ $selectedNote->id }}">
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatNroNota($selectedNote->nro_nota) }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatCustomerName($selectedNote->customer) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-base font-bold tracking-wide text-gray-950 dark:text-white">
                                    {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatPhoneDisplay($selectedNote->customer?->phone1_commercial ?: $selectedNote->customer?->phone) ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                @if ($selectedNote->reten)
                                    COMERCIAL RETÉN
                                @elseif ($selectedNote->comercial)
                                    {{ $selectedNote->comercial->empleado_id }}
                                    {{ trim($selectedNote->comercial->name . ' ' . $selectedNote->comercial->last_name) }}
                                @else
                                    Sin asignar
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-filament::button
                                    size="sm"
                                    color="danger"
                                    wire:click="removeFromSelection({{ $selectedNote->id }})"
                                >
                                    Quitar
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            @include('filament.superAdmin.resources.super-asignar-resource.partials.bulk-reassign-panel', [
                'selectedNoteIds' => $selectedNoteIds,
                'assignableOptions' => $this->assignableOptions,
            ])
        </div>
    </x-filament::section>
@endif
