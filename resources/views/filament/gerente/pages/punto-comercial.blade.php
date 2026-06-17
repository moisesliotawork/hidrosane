<x-filament-panels::page>
<style>
    .punto-comercial-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .punto-comercial-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        padding: 0.55rem 1rem;
        font-size: 0.875rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        border: 1px solid #86efac;
        background: #dcfce7;
        color: #14532d;
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .punto-comercial-tab:hover {
        background: #bbf7d0;
        border-color: #4ade80;
    }

    .punto-comercial-tab.is-active {
        background: #16a34a;
        color: #ffffff;
        border-color: #15803d;
        box-shadow: 0 2px 10px rgba(22, 163, 74, 0.28);
    }

    .punto-comercial-tab .pc-badge {
        border-radius: 9999px;
        padding: 0.125rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 700;
        background: rgba(20, 83, 45, 0.12);
        color: #14532d;
    }

    .punto-comercial-tab.is-active .pc-badge {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
    }

    html.dark .punto-comercial-tab {
        background: #14532d;
        color: #dcfce7;
        border-color: #166534;
    }

    html.dark .punto-comercial-tab:hover {
        background: #166534;
        border-color: #22c55e;
    }

    html.dark .punto-comercial-tab.is-active {
        background: #84cc16;
        color: #1a2e05;
        border-color: #a3e635;
        box-shadow: 0 2px 10px rgba(132, 204, 22, 0.25);
    }

    html.dark .punto-comercial-tab .pc-badge {
        background: rgba(220, 252, 231, 0.14);
        color: #dcfce7;
    }

    html.dark .punto-comercial-tab.is-active .pc-badge {
        background: rgba(26, 46, 5, 0.18);
        color: #1a2e05;
    }

    .pc-card {
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    html.dark .pc-card {
        border-color: #374151;
        background: #111827;
    }

    .pc-card-body {
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
        padding: 1rem;
    }

    .pc-fecha-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pc-dia-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.4rem;
        padding: 0.2rem 0.45rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        background: #14532d;
        color: #dcfce7;
        border: 1px solid #166534;
    }

    html.dark .pc-dia-badge {
        background: #166534;
        color: #bbf7d0;
        border-color: #22c55e;
    }

    .pc-fecha {
        font-size: 1.05rem;
        font-weight: 800;
        color: #111827;
        letter-spacing: 0.01em;
    }

    html.dark .pc-fecha {
        color: #f9fafb;
    }

    .pc-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #4b5563;
        margin-bottom: 0.2rem;
    }

    html.dark .pc-label {
        color: #9ca3af;
    }

    .pc-jefe {
        font-size: 0.95rem;
        font-weight: 700;
        color: #14532d;
        line-height: 1.35;
    }

    html.dark .pc-jefe {
        color: #86efac;
    }

    .pc-reporte {
        font-size: 0.92rem;
        line-height: 1.55;
        color: #1f2937;
        white-space: pre-wrap;
        word-break: break-word;
    }

    html.dark .pc-reporte {
        color: #e5e7eb;
    }

    .pc-btn-ir {
        display: inline-flex;
        width: 100%;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        padding: 0.85rem 1rem;
        font-size: 0.9rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        text-decoration: none;
        background: #16a34a;
        color: #ffffff;
        border: 1px solid #15803d;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.22);
    }

    .pc-btn-ir:hover {
        background: #15803d;
        color: #ffffff;
    }

    html.dark .pc-btn-ir {
        background: #22c55e;
        color: #052e16;
        border-color: #16a34a;
    }

    html.dark .pc-btn-ir:hover {
        background: #16a34a;
        color: #ffffff;
    }

    .pc-sin-gps {
        border-radius: 0.75rem;
        padding: 0.85rem 1rem;
        text-align: center;
        font-size: 0.88rem;
        font-weight: 600;
        color: #6b7280;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
    }

    html.dark .pc-sin-gps {
        color: #d1d5db;
        background: #1f2937;
        border-color: #374151;
    }

    .pc-empty {
        grid-column: 1 / -1;
        border-radius: 1rem;
        border: 1px dashed #d1d5db;
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: #6b7280;
        background: #fafafa;
    }

    html.dark .pc-empty {
        border-color: #4b5563;
        color: #9ca3af;
        background: #111827;
    }

    .pc-cards-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    @media (min-width: 640px) {
        .pc-cards-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (min-width: 1280px) {
        .pc-cards-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    .pc-date-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.6rem;
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border-radius: 0.85rem;
        border: 1px solid #d1d5db;
        background: #f9fafb;
    }

    html.dark .pc-date-filter {
        border-color: #374151;
        background: #1f2937;
    }

    .pc-date-filter label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #4b5563;
        margin-bottom: 0.35rem;
    }

    html.dark .pc-date-filter label {
        color: #9ca3af;
    }

    .pc-date-filter input[type="date"] {
        min-width: 11rem;
        padding: 0.55rem 0.75rem;
        border-radius: 0.6rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #111827;
        font-size: 0.92rem;
        font-weight: 600;
    }

    html.dark .pc-date-filter input[type="date"] {
        border-color: #4b5563;
        background: #111827;
        color: #f9fafb;
    }

    .pc-date-clear {
        padding: 0.55rem 0.9rem;
        border-radius: 0.6rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
    }

    html.dark .pc-date-clear {
        border-color: #4b5563;
        background: #111827;
        color: #e5e7eb;
    }

    .pc-date-clear:hover {
        background: #f3f4f6;
    }

    html.dark .pc-date-clear:hover {
        background: #374151;
    }

    .pc-page-wrap {
        margin-left: -0.5rem;
        margin-right: -0.5rem;
        padding: 0.75rem;
        border-radius: 0.85rem;
    }

    html:not(.dark) .pc-page-wrap {
        background: #ede4d8;
    }

    html.dark .pc-page-wrap {
        background: transparent;
        padding: 0;
        margin: 0;
    }

    .pc-page-surface {
        background: #14532d;
        padding: 1rem 0.75rem 1.5rem;
        border-radius: 0.85rem;
    }

    html.dark .pc-page-surface {
        background: transparent;
        padding: 0;
        border-radius: 0;
    }

    html:not(.dark) .pc-page-surface .pc-pagination {
        color: #dcfce7;
    }

    html:not(.dark) .pc-page-surface .pc-pagination a,
    html:not(.dark) .pc-page-surface .pc-pagination span,
    html:not(.dark) .pc-page-surface .pc-pagination button {
        color: #dcfce7 !important;
    }
</style>

<div class="pc-page-wrap">
<div class="pc-page-surface">

    {{-- Tabs --}}
    <div class="punto-comercial-tabs">
        @foreach ([
            'todos' => 'Todos',
            'hoy' => 'Hoy',
            'ayer' => 'Ayer',
        ] as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                class="punto-comercial-tab {{ $selectedTab === $key ? 'is-active' : '' }}"
            >
                {{ $label }}
                <span class="pc-badge">{{ $this->tabCounts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    {{-- Filtro por fecha específica --}}
    <div class="pc-date-filter">
        <div>
            <label for="fechaFiltro">Fecha específica</label>
            <input
                id="fechaFiltro"
                type="date"
                wire:model.live="fechaFiltro"
            />
        </div>
        @if (filled($fechaFiltro))
            <button type="button" wire:click="clearFechaFiltro" class="pc-date-clear">
                Limpiar
            </button>
        @endif
    </div>

    {{-- Cards --}}
    <div class="pc-cards-grid">
        @forelse ($this->reports as $report)
            @php
                $leader = $report->teamLeader;
                $leaderLabel = trim(($leader?->empleado_id ?? '—') . ' ' . ($leader?->name ?? '') . ' ' . ($leader?->last_name ?? ''));
                $mapsUrl = $report->mapsUrl();
                $diaBadge = $report->submitted_at
                    ? mb_strtoupper(mb_substr($report->submitted_at->locale('es')->isoFormat('dddd'), 0, 3))
                    : '—';
            @endphp

            <div class="pc-card">
                <div class="pc-card-body">
                    <div class="pc-fecha-row">
                        <span class="pc-dia-badge">{{ $diaBadge }}</span>
                        <p class="pc-fecha">
                            {{ $report->submitted_at?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="pc-label">Jefe/Equipo</p>
                        <p class="pc-jefe">{{ mb_strtoupper($leaderLabel) }}</p>
                    </div>

                    <div>
                        <p class="pc-label">Reporte del Punto/Comercial:</p>
                        <p class="pc-reporte">{{ $report->texto }}</p>
                    </div>

                    @if ($mapsUrl)
                        <a
                            href="{{ $mapsUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="pc-btn-ir"
                        >
                            GPS - IR
                        </a>
                    @else
                        <div class="pc-sin-gps">
                            Sin ubicación GPS
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="pc-empty">
                No hay reportes de punto comercial para este filtro.
            </div>
        @endforelse
    </div>

    <div class="pc-pagination">
        {{ $this->reports->links() }}
    </div>
</div>
</div>
</x-filament-panels::page>
