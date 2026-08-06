<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    @php
        $usuariosDeBaja = $this->getUsuariosDeBaja();
    @endphp

    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}

        <x-filament::section
            heading="USUARIOS DE BAJA"
            :description="$usuariosDeBaja->count() . ' usuario(s) con fecha de baja'"
            icon="heroicon-o-user-minus"
            icon-color="danger"
            :collapsible="true"
            :collapsed="true"
        >
            @if ($usuariosDeBaja->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay usuarios de baja.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full table-auto text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="px-2 py-2">ID</th>
                                <th class="px-2 py-2">Nombre</th>
                                <th class="px-2 py-2">Correo</th>
                                <th class="px-2 py-2">Teléfono</th>
                                <th class="px-2 py-2">Rol</th>
                                <th class="px-2 py-2">Fecha/Baja</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($usuariosDeBaja as $user)
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="px-2 py-2">
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                            {{ $user->empleado_id }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 font-medium">{{ $user->name }} {{ $user->last_name }}</td>
                                    <td class="px-2 py-2">{{ $user->email }}</td>
                                    <td class="px-2 py-2 font-semibold">
                                        {{ $user->phone ? chunk_split(str_replace(' ', '', (string) $user->phone), 3, ' ') : '—' }}
                                    </td>
                                    <td class="px-2 py-2">{{ $this->roleLabel($user->getRoleNames()->first()) }}</td>
                                    <td class="px-2 py-2 text-danger-600 dark:text-danger-400 font-semibold">
                                        {{ optional($user->baja)->timezone('Europe/Madrid')->format('d/m/Y') }}
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <a
                                            href="{{ $this->getResource()::getUrl('edit', ['record' => $user]) }}"
                                            class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
