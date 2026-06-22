<x-filament-panels::page>
<style>
    .pgc-wrap {
        margin: 0 -0.25rem;
        padding: 0 0.25rem 1rem;
    }

    html:not(.dark) .pgc-wrap {
        background: #f0e6d8;
    }

    .pgc-search {
        margin-bottom: 0.85rem;
        padding: 0.85rem 0.9rem;
        border-radius: 0.85rem;
        border: 1px solid #d1d5db;
        background: #faf5ef;
    }

    html.dark .pgc-search {
        border-color: #374151;
        background: #1f2937;
    }

    .pgc-search label {
        display: block;
        margin-bottom: 0.45rem;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #4b5563;
    }

    html.dark .pgc-search label {
        color: #9ca3af;
    }

    .pgc-search input {
        width: 100%;
        min-height: 44px;
        padding: 0.65rem 0.8rem;
        border-radius: 0.65rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #111827;
        font-size: 16px;
        font-weight: 600;
    }

    html.dark .pgc-search input {
        border-color: #4b5563;
        background: #111827;
        color: #f9fafb;
    }

    .pgc-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #6b7280;
    }

    html.dark .pgc-toolbar {
        color: #9ca3af;
    }

    .pgc-per-page {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .pgc-per-page button {
        min-width: 2.2rem;
        min-height: 2rem;
        padding: 0.25rem 0.55rem;
        border-radius: 0.45rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
        font-size: 0.75rem;
        font-weight: 800;
        cursor: pointer;
    }

    .pgc-per-page button.is-active {
        border-color: #16a34a;
        background: #16a34a;
        color: #ffffff;
    }

    html.dark .pgc-per-page button {
        border-color: #4b5563;
        background: #111827;
        color: #e5e7eb;
    }

    .pgc-cards {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    @media (min-width: 640px) {
        .pgc-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1024px) {
        .pgc-cards {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .pgc-card {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.9rem;
        border-radius: 0.9rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    html.dark .pgc-card {
        border-color: #374151;
        background: #111827;
    }

    .pgc-card.is-inhab {
        border-color: #dc2626;
        box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.25);
    }

    .pgc-card-header {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .pgc-name {
        color: #14532d;
        font-size: 1rem;
        font-weight: 900;
        line-height: 1.25;
        overflow-wrap: anywhere;
        text-transform: uppercase;
    }

    html.dark .pgc-name {
        color: #86efac;
    }

    .pgc-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
    }

    .pgc-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        line-height: 1.2;
        white-space: nowrap;
    }

    .pgc-badge-id {
        background: #e0f2fe;
        color: #075985;
    }

    .pgc-badge-dni {
        background: #f3f4f6;
        color: #374151;
    }

    html.dark .pgc-badge-dni {
        background: #374151;
        color: #e5e7eb;
    }

    .pgc-badge-ventas {
        background: #dcfce7;
        color: #14532d;
    }

    .pgc-badge-ventas.is-zero {
        background: #f3f4f6;
        color: #6b7280;
    }

    .pgc-badge-inhab {
        background: #fee2e2;
        color: #991b1b;
        font-size: 0.95rem;
    }

    .pgc-address-banner {
        padding: 0.55rem 0.7rem;
        border-radius: 0.55rem;
        background: #dbeafe;
        border: 1px solid #93c5fd;
        color: #1e3a8a;
        font-size: 0.82rem;
        font-weight: 700;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    html.dark .pgc-address-banner {
        background: #1e3a8a;
        border-color: #2563eb;
        color: #dbeafe;
    }

    .pgc-locality-banner {
        padding: 0.55rem 0.7rem;
        border-radius: 0.55rem;
        background: #e0f2fe;
        border: 1px solid #7dd3fc;
        color: #0c4a6e;
        font-size: 0.82rem;
        font-weight: 700;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    html.dark .pgc-locality-banner {
        background: #0c4a6e;
        border-color: #38bdf8;
        color: #e0f2fe;
    }

    .pgc-location-block {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .pgc-badge-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .pgc-badge-nota {
        background: #ffedd5;
        color: #9a3412;
        border: 1px solid #fdba74;
    }

    html.dark .pgc-badge-nota {
        background: #7c2d12;
        color: #ffedd5;
        border-color: #ea580c;
    }

    .pgc-badge-contrato {
        background: #dcfce7;
        color: #14532d;
        border: 1px solid #86efac;
        text-decoration: none;
    }

    a.pgc-badge-contrato:hover {
        background: #bbf7d0;
    }

    html.dark .pgc-badge-contrato {
        background: #14532d;
        color: #dcfce7;
        border-color: #22c55e;
    }

    .pgc-badge-phone {
        background: #fef9c3;
        color: #854d0e;
        border: 1px solid #fde047;
        text-decoration: none;
        min-height: 32px;
        padding: 0.35rem 0.65rem;
    }

    a.pgc-badge-phone:hover {
        background: #fef08a;
    }

    html.dark .pgc-badge-phone {
        background: #713f12;
        color: #fef9c3;
        border-color: #eab308;
    }

    .pgc-row {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .pgc-label {
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
    }

    html.dark .pgc-label {
        color: #9ca3af;
    }

    .pgc-value {
        font-size: 0.88rem;
        font-weight: 700;
        line-height: 1.35;
        color: #111827;
        overflow-wrap: anywhere;
    }

    html.dark .pgc-value {
        color: #f3f4f6;
    }

    .pgc-value.phones {
        color: #b45309;
    }

    html.dark .pgc-value.phones {
        color: #fbbf24;
    }

    .pgc-card-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.15rem;
        padding-top: 0.65rem;
        border-top: 1px solid #e5e7eb;
    }

    html.dark .pgc-card-footer {
        border-top-color: #374151;
    }

    .pgc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 44px;
        padding: 0.55rem 0.9rem;
        border-radius: 0.65rem;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        text-decoration: none;
        cursor: pointer;
        border: none;
    }

    .pgc-btn-view {
        flex: 1 1 auto;
        background: #2563eb;
        color: #ffffff;
    }

    .pgc-btn-gps {
        background: #16a34a;
        color: #ffffff;
    }

    .pgc-btn-icon {
        width: 1.1rem;
        height: 1.1rem;
        flex-shrink: 0;
    }

    .pgc-empty {
        padding: 2rem 1rem;
        border-radius: 0.85rem;
        border: 1px dashed #d1d5db;
        text-align: center;
        color: #6b7280;
        font-weight: 700;
    }

    html.dark .pgc-empty {
        border-color: #4b5563;
        color: #9ca3af;
    }

    .pgc-pagination {
        margin-top: 1rem;
    }
</style>

<div class="pgc-wrap">
    <div class="pgc-search">
        <label for="pgcSearch">Buscar cliente</label>
        <input
            id="pgcSearch"
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Nombre, DNI, teléfono, ID cliente…"
            autocomplete="off"
        />
    </div>

    <div class="pgc-toolbar">
        <span>{{ $this->customers->total() }} clientes</span>
        <div class="pgc-per-page">
            <span>Ver:</span>
            @foreach ([12, 20, 40] as $n)
                <button
                    type="button"
                    wire:click="$set('perPage', {{ $n }})"
                    @class(['is-active' => $perPage === $n])
                >{{ $n }}</button>
            @endforeach
        </div>
    </div>

    <div class="pgc-cards">
        @forelse ($this->customers as $customer)
            @php
                $clienteId = $customer->firstVentaClienteAdmin();
                $ventasCount = (int) ($customer->ventas_count ?? 0);
                $fullAddress = \App\Filament\Support\CustomerPosicionGlobalTable::streetAddress($customer);
                $locality = \App\Filament\Support\CustomerPosicionGlobalTable::locality($customer);
                $noteNumbers = \App\Filament\Support\CustomerPosicionGlobalTable::noteNumbers($customer);
                $contracts = \App\Filament\Support\CustomerPosicionGlobalTable::contractsForCard($customer);
                $clientPhoneBadges = \App\Filament\Support\CustomerPosicionGlobalTable::clientPhoneBadges($customer);
                $commercialPhoneBadges = \App\Filament\Support\CustomerPosicionGlobalTable::commercialPhoneBadges($customer);
                $gpsUrl = $customer->dentroGpsMapsUrl();
            @endphp

            <article @class(['pgc-card', 'is-inhab' => (bool) $customer->inhabilitado])>
                <div class="pgc-card-header">
                    <div class="pgc-name">{{ \App\Filament\Support\CustomerPosicionGlobalTable::displayName($customer) }}</div>
                    <div class="pgc-badges">
                        <span class="pgc-badge pgc-badge-id">ID {{ $clienteId }}</span>
                        @if (filled($customer->dni))
                            <span class="pgc-badge pgc-badge-dni">DNI {{ $customer->dni }}</span>
                        @endif
                        <span @class(['pgc-badge', 'pgc-badge-ventas', 'is-zero' => $ventasCount === 0])>
                            {{ $ventasCount }} {{ $ventasCount === 1 ? 'venta' : 'ventas' }}
                        </span>
                        @if ($customer->inhabilitado)
                            <span class="pgc-badge pgc-badge-inhab" title="Cliente inhabilitado">☠️ INHAB</span>
                        @endif
                    </div>
                </div>

                @if ($fullAddress !== '—' || $locality !== '—')
                    <div class="pgc-location-block">
                        @if ($fullAddress !== '—')
                            <div>
                                <span class="pgc-label">Domicilio</span>
                                <div class="pgc-address-banner">{{ $fullAddress }}</div>
                            </div>
                        @endif
                        @if ($locality !== '—')
                            <div>
                                <span class="pgc-label">Localidad</span>
                                <div class="pgc-locality-banner">{{ $locality }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                @if (! empty($noteNumbers))
                    <div class="pgc-badge-group">
                        <span class="pgc-label">Notas</span>
                        <div class="pgc-badges">
                            @foreach ($noteNumbers as $nroNota)
                                <span class="pgc-badge pgc-badge-nota">{{ $nroNota }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($contracts))
                    <div class="pgc-badge-group">
                        <span class="pgc-label">Contratos</span>
                        <div class="pgc-badges">
                            @foreach ($contracts as $contract)
                                <a href="{{ $contract['url'] }}" class="pgc-badge pgc-badge-contrato">{{ $contract['nro'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($clientPhoneBadges))
                    <div class="pgc-badge-group">
                        <span class="pgc-label">Teléfonos cliente</span>
                        <div class="pgc-badges">
                            @foreach ($clientPhoneBadges as $phone)
                                <a href="tel:{{ $phone['tel'] }}" class="pgc-badge pgc-badge-phone">{{ $phone['display'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($commercialPhoneBadges))
                    <div class="pgc-badge-group">
                        <span class="pgc-label">Tel. comercial</span>
                        <div class="pgc-badges">
                            @foreach ($commercialPhoneBadges as $phone)
                                <a href="tel:{{ $phone['tel'] }}" class="pgc-badge pgc-badge-phone">{{ $phone['display'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="pgc-card-footer">
                    <a href="{{ $this->viewUrl($customer) }}" class="pgc-btn pgc-btn-view">
                        Ver posición global
                    </a>
                    @if ($customer->hasDentroGps() && filled($gpsUrl))
                        <a href="{{ $gpsUrl }}" target="_blank" rel="noopener noreferrer" class="pgc-btn pgc-btn-gps">
                            <x-heroicon-o-map-pin class="pgc-btn-icon" />
                            GPS
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <div class="pgc-empty">
                No se encontraron clientes{{ filled($search) ? ' para "' . e($search) . '"' : '' }}.
            </div>
        @endforelse
    </div>

    @if ($this->customers->hasPages())
        <div class="pgc-pagination">
            {{ $this->customers->links() }}
        </div>
    @endif
</div>
</x-filament-panels::page>
