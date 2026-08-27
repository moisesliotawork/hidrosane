<x-filament-panels::page>
    @php
        $monthBadges = $this->monthBadges();
        $selectedBadgeMonth = $this->selectedBadgeMonth();
        $selectedBadgeYear = $this->selectedBadgeYear();
        $showAll = $this->showAllMonths;
        $tabYears = $this->tabYears();
        $tabStyle = static function (bool $active): string {
            return $active
                ? 'background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;font-weight:700;'
                : 'background:#fff;color:#111827;border:1px solid #9ca3af;font-weight:600;';
        };
        $periodLabel = $this->selectedPeriodLabel();
        $clienteQ = $this->clienteSearchQuery();
        $activityByYear = $clienteQ !== '' ? $this->clienteActivityByYear() : [];
        $hasClienteFilter = $clienteQ !== '';
        $hasAnyActivity = $hasClienteFilter && $activityByYear !== [];
    @endphp

    <style>
        .lista-amano-compact .fi-ta-header-cell {
            padding: 0.2rem 0.4rem !important;
            font-size: 0.75rem !important;
        }
        .lista-amano-compact .fi-ta-cell {
            padding-block: 0.15rem !important;
        }
        .lista-amano-compact .fi-ta-text-item-label {
            line-height: 1.25 !important;
            font-size: 0.8125rem !important;
        }
        .lista-amano-compact .fi-ta-text > p.text-sm {
            font-size: 0.625rem !important;
            line-height: 1.1 !important;
            font-weight: 600 !important;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 0 !important;
            color: #6b7280 !important;
        }
        .lista-amano-month-bar {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .lista-amano-has-activity {
            position: relative;
        }
        .lista-amano-has-activity::after {
            content: '';
            position: absolute;
            top: -3px;
            right: -3px;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: #16a34a;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #16a34a;
        }
    </style>

    <div class="flex flex-col gap-y-4 lista-amano-compact">
        <div style="display: flex; flex-direction: column; gap: 0.35rem;">
            <div style="padding: 0.2rem 0.1rem 0;">
                <button
                    type="button"
                    wire:click="showAllPayments"
                    style="height: 1.55rem; padding: 0 0.75rem; border-radius: 0.25rem; font-size: 0.72rem; cursor: pointer; white-space: nowrap; {{ $tabStyle($showAll) }}"
                >
                    Todos
                </button>
            </div>

            @foreach ($tabYears as $year)
                @php
                    $yearActivity = $activityByYear[$year] ?? [];
                @endphp
                <div class="lista-amano-month-bar" style="display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; width: 100%; padding: 0.15rem 0.1rem;">
                    <span style="flex: 0 0 auto; min-width: 2.6rem; font-size: 0.68rem; font-weight: 800; color: {{ $selectedBadgeYear === (int) $year && ! $showAll ? '#1d4ed8' : '#6b7280' }};">
                        {{ $year }}
                    </span>

                    @foreach ($monthBadges as $monthNum => $badge)
                        @php
                            $isSelected = ! $showAll
                                && $selectedBadgeYear === (int) $year
                                && $selectedBadgeMonth === (int) $monthNum;
                            $hasActivity = $hasClienteFilter && in_array((int) $monthNum, $yearActivity, true);
                            $badgeStyle = $isSelected
                                ? "background:{$badge['text']};color:#ffffff;border:2px solid {$badge['text']};font-weight:900;outline:3px solid {$badge['border']};outline-offset:2px;box-shadow:0 2px 8px rgb(0 0 0 / 0.18);z-index:1;position:relative;"
                                : ($hasActivity
                                    ? "background:{$badge['bg']};color:{$badge['text']};border:2px solid #16a34a;font-weight:800;opacity:1;"
                                    : "background:{$badge['bg']};color:{$badge['text']};border:1px solid {$badge['border']};font-weight:600;opacity:" . ($hasClienteFilter ? '.4' : '.72') . ";");
                        @endphp
                        <button
                            type="button"
                            wire:click="selectCalendarMonth({{ $year }}, {{ $monthNum }})"
                            title="{{ $hasActivity ? 'Hay actividad de «'.$clienteQ.'» en '.($badge['full'] ?? $badge['label']).' '.$year : 'Filtrar por '.($badge['full'] ?? $badge['label']).' '.$year }}"
                            class="{{ $hasActivity ? 'lista-amano-has-activity' : '' }}"
                            style="flex: 0 0 auto; height: 1.55rem; min-width: 2.35rem; padding: 0 0.4rem; border-radius: 999px; font-size: 0.62rem; letter-spacing: 0.02em; cursor: pointer; line-height: 1; white-space: nowrap; {{ $badgeStyle }}"
                        >
                            {{ $badge['label'] }}
                        </button>
                    @endforeach
                </div>
            @endforeach

            @if ($periodLabel)
                <p style="margin: 0; font-size: 0.75rem; color: #6b7280; padding-left: 0.2rem;">
                    Mostrando: <strong style="color:#111827;">{{ $periodLabel }}</strong>
                    @if ($hasClienteFilter)
                        · Cliente: <strong style="color:#1d4ed8;">{{ $clienteQ }}</strong>
                        @if ($hasAnyActivity)
                            · Actividad en:
                            <strong style="color:#15803d;">
                                @foreach ($activityByYear as $actYear => $months)
                                    @foreach ($months as $m)
                                        {{ $monthBadges[$m]['full'] ?? $monthBadges[$m]['label'] ?? $m }} {{ $actYear }}@if (! ($loop->parent->last && $loop->last)), @endif
                                    @endforeach
                                @endforeach
                            </strong>
                        @else
                            · <span style="color:#b91c1c;">sin actividad en 2025–2026</span>
                        @endif
                    @endif
                </p>
            @endif
        </div>

        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
