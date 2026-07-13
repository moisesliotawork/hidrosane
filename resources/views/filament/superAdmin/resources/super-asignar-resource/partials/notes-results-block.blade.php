@if ($listSearchMessage)
    <div class="mt-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100">
        {{ $listSearchMessage }}
    </div>
@endif

@if (count($resultNoteIds) > 0)
    <div class="mt-4 space-y-3">
        @if ($matchedCustomersLabel)
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                Cliente(s): {{ $matchedCustomersLabel }}
            </p>
        @endif

        @if ($matchedCustomersPhones)
            <p class="text-sm text-gray-700 dark:text-gray-200">
                Teléfono(s):
                <span class="text-base font-bold tracking-wide text-gray-950 dark:text-white">
                    {{ $matchedCustomersPhones }}
                </span>
            </p>
        @endif

        <div class="flex flex-wrap gap-3">
            <x-filament::button size="sm" color="primary" wire:click="selectAllResultNotes">
                Seleccionar todas (hasta {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::MAX_SELECTED_NOTES }})
            </x-filament::button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Sel.</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">No. Nota</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Cliente</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Teléfono</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Fecha nota</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Fecha asignación</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Fecha visita</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Asignación actual</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Estado</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-950">
                    @foreach ($this->resultNotes as $resultNote)
                        @include('filament.superAdmin.resources.super-asignar-resource.partials.phone-note-row', [
                            'phoneNote' => $resultNote,
                            'assignableOptions' => $this->assignableOptions,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
