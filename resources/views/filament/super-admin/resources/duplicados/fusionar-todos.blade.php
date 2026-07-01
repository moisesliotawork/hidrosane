<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
            <p class="font-semibold">Criterio de fusión automática</p>
            <p class="mt-1">Solo pares de <strong>2 clientes</strong> con el <strong>mismo nombre completo</strong> y al menos <strong>un teléfono compartido</strong>. Desmarca cualquier registro que no quieras fusionar.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-filament::button size="sm" color="gray" wire:click="selectAll">
                    Marcar todos
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="deselectAll">
                    Desmarcar todos
                </x-filament::button>
            </div>
        </div>

        @if ($pairs === [])
            <div class="rounded-xl border border-warning-500/30 bg-warning-500/10 p-4 text-sm text-warning-700 dark:text-warning-300">
                No hay pares fusionables de 2 clientes con mismo nombre y teléfono compartido.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold">Incluir</th>
                            <th class="px-3 py-3 text-left font-semibold">Nombre</th>
                            <th class="px-3 py-3 text-left font-semibold">ID</th>
                            <th class="px-3 py-3 text-left font-semibold">Teléfono 1</th>
                            <th class="px-3 py-3 text-left font-semibold">Teléfono 2</th>
                            <th class="px-3 py-3 text-left font-semibold">Teléfono 3</th>
                            <th class="px-3 py-3 text-left font-semibold">DNI</th>
                            <th class="px-3 py-3 text-left font-semibold">Notas</th>
                            <th class="px-3 py-3 text-left font-semibold">Ventas</th>
                            <th class="px-3 py-3 text-left font-semibold">Creado</th>
                            <th class="px-3 py-3 text-left font-semibold">Rol</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pairs as $index => $pair)
                            @php
                                $rowColors = [
                                    'bg-emerald-50 dark:bg-emerald-950/40',
                                    'bg-sky-50 dark:bg-sky-950/40',
                                    'bg-amber-50 dark:bg-amber-950/40',
                                    'bg-violet-50 dark:bg-violet-950/40',
                                ];
                                $rowColor = $rowColors[$index % count($rowColors)];
                            @endphp

                            @foreach ($pair['customers'] as $customer)
                                <tr class="{{ $rowColor }} border-b border-gray-200/70 dark:border-white/10">
                                    <td class="px-3 py-3 align-middle">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                                            wire:model.live="selectedCustomerIds"
                                            value="{{ $customer['id'] }}"
                                        />
                                    </td>
                                    <td class="px-3 py-3 font-semibold whitespace-nowrap">{{ $customer['name'] }}</td>
                                    <td class="px-3 py-3">#{{ $customer['id'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['phone'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['secondary_phone'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['third_phone'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['dni'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['notes_count'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['ventas_count'] }}</td>
                                    <td class="px-3 py-3">{{ $customer['created_at'] }}</td>
                                    <td class="px-3 py-3">
                                        @if ($customer['role'] === 'keeper')
                                            <span class="inline-flex rounded-full bg-success-500/15 px-2 py-1 text-xs font-semibold text-success-700 dark:text-success-300">Se conserva</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-danger-500/15 px-2 py-1 text-xs font-semibold text-danger-700 dark:text-danger-300">Se fusiona</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="{{ $rowColor }}">
                                <td colspan="11" class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">
                                    Teléfono(s) compartido(s):
                                    <span class="font-semibold">{{ implode(' | ', array_map(fn ($phone) => \App\Services\CustomerDuplicateSearchService::formatPhoneDisplay($phone), $pair['shared_phones'])) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
