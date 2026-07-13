<div class="rounded-lg border border-primary-200 bg-white p-4 dark:border-primary-800 dark:bg-gray-900">
    <p class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
        Reasignación masiva · {{ count($selectedNoteIds) }} nota(s) seleccionada(s)
    </p>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-gray-950 dark:text-white">Asignar a</label>
            <select
                wire:model="bulkAssignmentData.comercial_id"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            >
                @foreach ($assignableOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-medium text-gray-950 dark:text-white">Fecha de asignación</label>
            <input
                type="date"
                wire:model="bulkAssignmentData.assignment_date"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Si se deja vacío, se usará la fecha actual al asignar un comercial.
            </p>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <x-filament::button color="success" wire:click="assignSelectedNotes" wire:loading.attr="disabled">
            Confirmar reasignación masiva ({{ count($selectedNoteIds) }})
        </x-filament::button>

        <x-filament::button color="gray" wire:click="clearSelection">
            Vaciar selección
        </x-filament::button>
    </div>
</div>
