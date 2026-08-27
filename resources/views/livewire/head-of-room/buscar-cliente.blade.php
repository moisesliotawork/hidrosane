<div>
    {{ $this->form }}

    @if (count($customerChoices) > 0)
        <div class="mt-6 space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
            <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">
                Varios clientes comparten el teléfono {{ $searchedDigits }}.
                Elige a cuál asociar la nota (un teléfono puede tener más de un cliente).
            </p>

            <ul class="space-y-2">
                @foreach ($customerChoices as $choice)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-white px-3 py-2 dark:border-amber-800 dark:bg-gray-900">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $choice['label'] }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">
                                ID {{ $choice['id'] }}
                                @if ($choice['phones'])
                                    · {{ $choice['phones'] }}
                                @endif
                            </div>
                        </div>
                        <x-filament::button
                            size="sm"
                            color="warning"
                            wire:click="chooseCustomer({{ $choice['id'] }})"
                        >
                            Crear nota para este cliente
                        </x-filament::button>
                    </li>
                @endforeach
            </ul>

            <div class="pt-2">
                <x-filament::button
                    size="sm"
                    color="gray"
                    wire:click="createNoteForNewCustomerWithSamePhone"
                >
                    Crear nota con cliente NUEVO (mismo teléfono)
                </x-filament::button>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
