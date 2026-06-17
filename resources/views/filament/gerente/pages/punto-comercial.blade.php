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
</style>

<div style="padding:0 4px 24px">

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

    {{-- Cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->reports as $report)
            @php
                $leader = $report->teamLeader;
                $leaderLabel = trim(($leader?->empleado_id ?? '—') . ' ' . ($leader?->name ?? '') . ' ' . ($leader?->last_name ?? ''));
                $mapsUrl = $report->mapsUrl();
            @endphp

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="bg-sky-600 px-4 py-3 text-white">
                    <p class="text-xs font-semibold uppercase tracking-wide opacity-90">Punto Comercial de</p>
                    <p class="mt-1 text-base font-bold leading-tight">{{ mb_strtoupper($leaderLabel) }}</p>
                </div>

                <div class="space-y-3 px-4 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha y hora</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $report->submitted_at?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Escrito</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                            {{ $report->texto }}
                        </p>
                    </div>

                    @if ($mapsUrl)
                        <a
                            href="{{ $mapsUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold uppercase tracking-wide text-white shadow transition hover:bg-emerald-700"
                        >
                            IR
                        </a>
                    @else
                        <div class="rounded-xl bg-gray-100 px-4 py-3 text-center text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            Sin ubicación GPS
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No hay reportes de punto comercial para este filtro.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->reports->links() }}
    </div>
</div>
</x-filament-panels::page>
