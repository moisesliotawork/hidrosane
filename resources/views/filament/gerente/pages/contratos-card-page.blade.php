<x-filament-panels::page>
<div style="padding:0 6px 0 2px">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-48">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Buscar nombre, teléfono, nº contrato, nº nota…"
                />
            </x-filament::input.wrapper>
        </div>

        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <span>Ordenar:</span>
            <button wire:click="setSort('created_at')"
                class="px-2 py-1 rounded {{ $sortBy === 'created_at' ? 'bg-lime-600 text-white' : 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-gray-100' }}">
                Fecha {{ $sortBy === 'created_at' ? ($sortDir === 'desc' ? '↓' : '↑') : '' }}
            </button>
            <button wire:click="setSort('nro_contr_adm')"
                class="px-2 py-1 rounded {{ $sortBy === 'nro_contr_adm' ? 'bg-lime-600 text-white' : 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-gray-100' }}">
                Nº Contrato {{ $sortBy === 'nro_contr_adm' ? ($sortDir === 'desc' ? '↓' : '↑') : '' }}
            </button>
        </div>

        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <span>Ver:</span>
            @foreach ([12, 24, 48] as $n)
                <button wire:click="$set('perPage', {{ $n }})"
                    class="px-2 py-1 rounded {{ $perPage == $n ? 'bg-lime-600 text-white' : 'bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-gray-100' }}">
                    {{ $n }}
                </button>
            @endforeach
        </div>

        <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
            {{ $this->ventas->total() }} contratos
        </span>
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3" style="padding:0 4px;min-width:0;width:100%">
        @forelse ($this->ventas as $venta)
            @php
                $customer = $venta->customer;
                $estado   = $venta->estado_venta;

                // Colores inline por estado: [borderColor, cardBg, headerBg, badgeBg, badgeText]
                $palette = match($estado?->value) {
                    'en_revision'        => ['#93c5fd', '#eff6ff', '#3b82f6', '#1e3a8a', '#ffffff'],
                    'comite'             => ['#fca5a5', '#fef2f2', '#ef4444', '#7f1d1d', '#ffffff'],
                    'stand_by'           => ['#bef264', '#f7fee7', '#84cc16', '#3f6212', '#ffffff'],
                    'en_reparto'         => ['#86efac', '#f0fdf4', '#22c55e', '#14532d', '#ffffff'],
                    'nulo_en_reparto'    => ['#fdba74', '#fff7ed', '#f97316', '#7c2d12', '#ffffff'],
                    'facturado'          => ['#9ca3af', '#f9fafb', '#6b7280', '#374151', '#ffffff'],
                    'pendiente_de_cobro' => ['#fcd34d', '#fffbeb', '#f59e0b', '#78350f', '#ffffff'],
                    'retroceso'          => ['#fb923c', '#fff7ed', '#ea580c', '#7c2d12', '#ffffff'],
                    'nulo_financiero'    => ['#5eead4', '#f0fdfa', '#14b8a6', '#134e4a', '#ffffff'],
                    'no_sale_a_calle'    => ['#c4b5fd', '#faf5ff', '#8b5cf6', '#3b0764', '#ffffff'],
                    'nulo_por_ausente'   => ['#a3e635', '#f7fee7', '#65a30d', '#365314', '#ffffff'],
                    default              => ['#d1d5db', '#ffffff', '#9ca3af', '#374151', '#ffffff'],
                };

                $fmt = fn(?string $p): string => $p
                    ? implode(' ', str_split(preg_replace('/\D/', '', $p), 3))
                    : '';

                $phonesClient = collect([$customer?->phone, $customer?->secondary_phone, $customer?->third_phone])
                    ->filter()->map($fmt)->values();

                $phonesCom = collect([$customer?->phone1_commercial, $customer?->phone2_commercial])
                    ->filter()->map($fmt)->values();

                $direccion = collect([
                    $customer?->primary_address,
                    $customer?->nro_piso,
                    $customer?->ciudad,
                    $customer?->postal_code ? 'CP '.$customer->postal_code : null,
                ])->filter()->join(', ');

                $ofertasConProductos = $venta->ventaOfertas->map(fn($vo) => [
                    'nombre'    => strtoupper($vo->oferta?->nombre ?? ''),
                    'productos' => $vo->productos->map(fn($vop) => strtoupper($vop->producto?->nombre ?? ''))->filter()->values(),
                ])->filter(fn($o) => $o['nombre'] !== '')->values();

                $editUrl = \App\Filament\Gerente\Resources\VentaResource::getUrl('edit', ['record' => $venta]);
            @endphp

            <div x-data="{ open: false }" style="border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.12),inset 0 0 0 2px {{ $palette[0] }};display:flex;flex-direction:column;overflow:hidden;min-width:0;max-width:100%;background:{{ $palette[1] }}">

                {{-- Cabecera --}}
                <div style="padding:8px 12px;display:flex;flex-direction:column;gap:6px;background:{{ $palette[2] }};border-bottom:1px solid {{ $palette[0] }}">
                    {{-- Fila 1: números --}}
                    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap">
                        @if($venta->nro_contr_adm)
                            <span style="font-size:19px;font-weight:800;padding:2px 8px;border-radius:4px;background:#dcfce7;color:#14532d;font-family:'Inter','Segoe UI',system-ui,sans-serif">
                                Contr: {{ $venta->nro_contr_adm }}
                            </span>
                        @endif
                        @if($venta->note)
                            <span style="font-size:19px;font-weight:800;padding:2px 8px;border-radius:4px;background:#fce7f3;color:#9d174d;font-family:'Inter','Segoe UI',system-ui,sans-serif">
                                Nota: {{ $venta->note->nro_nota }}
                            </span>
                        @endif
                        @if($venta->nro_cliente_adm)
                            <span style="font-size:15px;padding:2px 8px;border-radius:4px;background:#e0f2fe;color:#075985;font-family:monospace">
                                CL{{ $venta->nro_cliente_adm }}
                            </span>
                        @endif
                    </div>
                    {{-- Fila 2: izquierda (estado, teleop, fuente) + derecha (VER CONTRATO) --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                        {{-- Izquierda --}}
                        <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;min-width:0">
                            @if($estado)
                                <span style="font-size:15px;font-weight:700;padding:3px 9px;border-radius:4px;background:{{ $palette[3] }};color:{{ $palette[4] }}">
                                    {{ $estado->label() }}
                                </span>
                            @endif
                            @if($venta->note?->user?->empleado_id)
                                <span style="font-size:15px;font-weight:700;padding:3px 10px;border-radius:4px;background:#9d174d;color:#ffffff;font-family:monospace">
                                    {{ $venta->note->user->empleado_id }}
                                </span>
                            @endif
                            @if($venta->note?->fuente)
                                @php
                                    $fuenteBg = match($venta->note->fuente->value) {
                                        'CALLE'    => '#ea580c',
                                        'VIP-INT'  => '#16a34a',
                                        'VIP-EXT'  => '#a16207',
                                        'PtaFria'  => '#dc2626',
                                        'excel'    => '#0284c7',
                                        default    => '#6b7280',
                                    };
                                @endphp
                                <span style="font-size:15px;font-weight:700;padding:3px 10px;border-radius:4px;background:{{ $fuenteBg }};color:#ffffff">
                                    {{ $venta->note->fuente->getLabel() }}
                                </span>
                            @endif
                            <a href="#" @click.prevent="open = !open"
                                :style="open ? 'background:#6d28d9' : 'background:#7c3aed'"
                                style="display:inline-flex;align-items:center;gap:4px;font-size:15px;font-weight:700;padding:3px 14px;border-radius:4px;background:#7c3aed;color:#ffffff;text-decoration:none;white-space:nowrap">
                                <span style="display:inline-flex;align-items:center;line-height:0"><x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" class="w-3.5 h-3.5" /></span>VER OBS
                            </a>
                        </div>
                        {{-- Derecha --}}
                        <a href="{{ $editUrl }}" style="display:inline-flex;align-items:center;gap:4px;font-size:15px;font-weight:700;padding:3px 10px;border-radius:4px;background:#16a34a;color:#ffffff;text-decoration:none;white-space:nowrap;flex-shrink:0">
                            <x-filament::icon icon="heroicon-m-eye" class="w-3.5 h-3.5" />
                            VER CONTRATO
                        </a>
                    </div>
                </div>

                {{-- Cuerpo --}}
                <div style="padding:10px 12px 12px;display:flex;flex-direction:column;gap:6px;flex:1;color:#111827;font-family:'Inter','Segoe UI',system-ui,sans-serif;background:#ffffff">

                    {{-- Nombre --}}
                    <p style="font-size:22px;font-weight:800;color:#000000;line-height:1.15;text-transform:uppercase;letter-spacing:.03em;font-family:'Helvetica Neue','Helvetica',system-ui,sans-serif;background:#1e3a8a;color:#ffffff;margin:-10px -12px 0;padding:10px 12px 8px">
                        {{ strtoupper(trim(($customer?->first_names ?? '') . ' ' . ($customer?->last_names ?? ''))) ?: '—' }}
                    </p>

                    {{-- Teléfonos cliente: 3 en una línea --}}
                    @if($phonesClient->isNotEmpty())
                        <div style="display:flex;flex-wrap:nowrap;gap:3px;overflow:hidden;min-width:0">
                            @foreach($phonesClient as $p)
                                <span style="font-size:15px;font-weight:700;padding:2px 6px;border-radius:3px;background:#be185d;color:#ffffff;white-space:nowrap">
                                    {{ $p }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Teléfonos comercial: en una línea --}}
                    @if($phonesCom->isNotEmpty())
                        <div style="display:flex;flex-wrap:nowrap;gap:3px;overflow:hidden;min-width:0">
                            @foreach($phonesCom as $p)
                                <span style="font-size:15px;font-weight:700;padding:2px 6px;border-radius:3px;background:#a16207;color:#ffffff;white-space:nowrap;opacity:.9">
                                    {{ $p }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Dirección completa --}}
                    @if($direccion)
                        <p style="font-size:17px;font-weight:800;color:#000000;line-height:1.4">
                            📍 {{ $direccion }}
                        </p>
                    @endif

                    <div style="border-top:1px solid rgba(0,0,0,.1);padding-top:6px;margin-top:1px;display:flex;flex-direction:column;gap:4px">

                        {{-- Venta + Entrega --}}
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            @if($venta->fecha_venta)
                                <span style="font-size:15px;font-weight:700;padding:3px 7px;border-radius:4px;background:#ea580c;color:#ffffff;white-space:nowrap">
                                    Venta: {{ $venta->fecha_venta->format('d/m/Y H:i') }}
                                </span>
                            @endif
                            @if($venta->fecha_entrega)
                                <span style="font-size:15px;font-weight:700;padding:3px 7px;border-radius:4px;background:#c2410c;color:#ffffff;white-space:nowrap">
                                    Entrega: {{ \Carbon\Carbon::parse($venta->fecha_entrega)->format('d/m/Y') }}{{ $venta->horario_entrega ? ' '.$venta->horario_entrega : '' }}
                                </span>
                            @endif
                        </div>

                        {{-- Comercial + Compañero --}}
                        @php
                            $comercialUser = $venta->note?->comercial ?? $venta->comercial;
                        @endphp
                        <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
                            @if($comercialUser)
                                <span style="display:inline-block;font-size:15px;font-weight:800;padding:2px 9px;border-radius:9999px;background:#dbeafe;color:#1e3a8a;text-transform:uppercase;letter-spacing:.04em">
                                    👤 {{ trim(($comercialUser->empleado_id ? $comercialUser->empleado_id.' - ' : '').$comercialUser->name.' '.($comercialUser->last_name ?? '')) }}
                                </span>
                            @endif
                            @if($venta->companion)
                                <span style="display:inline-block;font-size:15px;font-weight:800;padding:2px 9px;border-radius:9999px;background:#dbeafe;color:#1e3a8a;text-transform:uppercase;letter-spacing:.04em">
                                    👤 {{ trim(($venta->companion->empleado_id ? $venta->companion->empleado_id.' - ' : '').$venta->companion->name.' '.($venta->companion->last_name ?? '')) }}
                                </span>
                            @endif
                        </div>

                    </div>

                    {{-- Ofertas y productos --}}
                    @if($ofertasConProductos->isNotEmpty())
                        <div style="border-top:1px solid rgba(0,0,0,.1);padding-top:5px;display:flex;flex-direction:column;align-items:flex-start;gap:5px">
                            @foreach($ofertasConProductos as $oferta)
                                <div style="display:flex;flex-direction:column;align-items:flex-start;gap:2px">
                                    <span style="font-size:15px;font-weight:700;padding:3px 8px;border-radius:3px;background:#1e3a8a;color:#ffffff;text-transform:uppercase;letter-spacing:.04em">
                                        {{ $oferta['nombre'] }}
                                    </span>
                                    @foreach($oferta['productos'] as $prod)
                                        <span style="font-size:14px;font-weight:600;padding:2px 8px 2px 14px;border-radius:3px;background:#3b5bdb;color:#ffffff;text-transform:uppercase;letter-spacing:.03em">
                                            › {{ $prod }}
                                        </span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>

                {{-- Panel de observaciones (toggle) --}}
                @php
                    $obsComercial    = $venta->note ? ($venta->note->getRelation('observations') ?? collect()) : collect();
                    $obsSala         = $venta->note ? ($venta->note->getRelation('observacionesSala') ?? collect()) : collect();
                    $obsRepartidor   = trim($venta->observaciones_repartidor ?? '');
                    $hasObs = $obsComercial->isNotEmpty() || $obsSala->isNotEmpty() || $obsRepartidor !== '';
                @endphp
                <div x-show="open" x-cloak style="border-top:2px solid #7c3aed;padding:10px 12px;background:#faf5ff;display:flex;flex-direction:column;gap:8px">
                    @if($obsComercial->isNotEmpty())
                        <div>
                            <p style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#6d28d9;margin-bottom:4px">Obs. Teleoperadora</p>
                            @foreach($obsComercial->sortByDesc('created_at') as $obs)
                                @if(trim($obs->observation ?? ''))
                                    <div style="font-size:15px;color:#1f2937;padding:4px 8px;border-left:3px solid #7c3aed;background:#ede9fe;border-radius:0 4px 4px 0;margin-bottom:3px">
                                        <div style="font-size:11px;color:#7c3aed;margin-bottom:2px">{{ $obs->created_at?->format('d/m/Y H:i') }}</div>
                                        <span style="font-weight:700;color:#6d28d9">{{ $obs->author?->name ?? '—' }}:</span>
                                        {{ $obs->observation }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    @if($obsSala->isNotEmpty())
                        <div>
                            <p style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#0369a1;margin-bottom:4px">Obs. Jefe de Sala</p>
                            @foreach($obsSala->sortByDesc('created_at') as $obs)
                                @if(trim($obs->observation ?? ''))
                                    <div style="font-size:15px;color:#1f2937;padding:4px 8px;border-left:3px solid #0369a1;background:#e0f2fe;border-radius:0 4px 4px 0;margin-bottom:3px">
                                        <div style="font-size:11px;color:#0369a1;margin-bottom:2px">{{ $obs->created_at?->format('d/m/Y H:i') }}</div>
                                        <span style="font-weight:700;color:#0369a1">{{ $obs->author?->name ?? '—' }}:</span>
                                        {{ $obs->observation }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    @if($obsRepartidor !== '')
                        <div>
                            <p style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#b45309;margin-bottom:4px">Obs. Comercial</p>
                            <div style="font-size:15px;color:#1f2937;padding:4px 8px;border-left:3px solid #b45309;background:#fef3c7;border-radius:0 4px 4px 0">
                                @if($venta->fecha_venta)
                                    <div style="font-size:11px;color:#b45309;margin-bottom:2px">{{ $venta->fecha_venta->format('d/m/Y H:i') }}</div>
                                @endif
                                {{ $obsRepartidor }}
                            </div>
                        </div>
                    @endif
                    @if(!$hasObs)
                        <p style="font-size:15px;color:#9ca3af;font-style:italic">Sin observaciones</p>
                    @endif
                </div>

            </div>
        @empty
            <div class="col-span-full text-center py-12 text-gray-400 dark:text-gray-500">
                <p class="text-sm">No se encontraron contratos</p>
            </div>
        @endforelse
    </div>

    {{-- Paginación --}}
    @if($this->ventas->hasPages())
        <div class="mt-5">
            {{ $this->ventas->links() }}
        </div>
    @endif

</div>
</x-filament-panels::page>
