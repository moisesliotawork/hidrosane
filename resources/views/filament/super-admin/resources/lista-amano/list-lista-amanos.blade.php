<x-filament-panels::page>
    @php
        $monthBadges = $this->monthBadges();
        $selectedBadgeMonth = $this->selectedBadgeMonth();
        $showAll = $this->showAllMonths;
        $years = $this->availableYears();
        $currentYear = (int) now()->year;
        $isCurrentYearSelected = (int) $this->selectedYear === $currentYear;
        $yearSelectStyle = $isCurrentYearSelected
            ? 'background:#dbeafe;color:#1d4ed8;border:1px solid #1d4ed8;font-weight:800;'
            : 'background:#f3f4f6;color:#6b7280;border:1px solid #9ca3af;font-weight:700;';
        $tabStyle = static function (bool $active): string {
            return $active
                ? 'background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;font-weight:700;'
                : 'background:#fff;color:#111827;border:1px solid #9ca3af;font-weight:600;';
        };
        $periodLabel = $this->selectedPeriodLabel();
        $clienteQ = $this->clienteSearchQuery();
        $monthsWithActivity = $clienteQ !== '' ? $this->monthsWithClienteActivity() : [];
        $hasClienteFilter = $clienteQ !== '';
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
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div class="lista-amano-month-bar" style="display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; width: 100%; padding: 0.45rem 0.1rem 0.45rem 0.2rem;">
                <button
                    type="button"
                    wire:click="showAllPayments"
                    style="flex: 0 0 auto; height: 1.85rem; padding: 0 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer; white-space: nowrap; {{ $tabStyle($showAll) }}"
                >
                    Todos
                </button>

                @foreach ($monthBadges as $monthNum => $badge)
                    @php
                        $isSelected = ! $showAll && $selectedBadgeMonth === (int) $monthNum;
                        $hasActivity = $hasClienteFilter && in_array((int) $monthNum, $monthsWithActivity, true);
                        $badgeStyle = $isSelected
                            ? "background:{$badge['text']};color:#ffffff;border:2px solid {$badge['text']};font-weight:900;outline:3px solid {$badge['border']};outline-offset:2px;box-shadow:0 2px 8px rgb(0 0 0 / 0.18);z-index:1;position:relative;"
                            : ($hasActivity
                                ? "background:{$badge['bg']};color:{$badge['text']};border:2px solid #16a34a;font-weight:800;opacity:1;"
                                : "background:{$badge['bg']};color:{$badge['text']};border:1px solid {$badge['border']};font-weight:600;opacity:" . ($hasClienteFilter ? '.4' : '.72') . ";");
                    @endphp
                    <button
                        type="button"
                        wire:click="selectCalendarMonth({{ $monthNum }})"
                        title="{{ $hasActivity ? 'Hay actividad de «'.$clienteQ.'» en '.($badge['full'] ?? $badge['label']).' '.$this->selectedYear : 'Filtrar por '.($badge['full'] ?? $badge['label']).' '.$this->selectedYear }}"
                        class="{{ $hasActivity ? 'lista-amano-has-activity' : '' }}"
                        style="flex: 0 0 auto; height: 1.55rem; min-width: 2.35rem; padding: 0 0.4rem; border-radius: 999px; font-size: 0.62rem; letter-spacing: 0.02em; cursor: pointer; line-height: 1; white-space: nowrap; {{ $badgeStyle }}"
                    >
                        {{ $badge['label'] }}
                    </button>
                @endforeach

                <label style="display: inline-flex; align-items: center; gap: 0.35rem; flex: 0 0 auto;">
                    <span style="font-size: 0.75rem; font-weight: 600; color: {{ $isCurrentYearSelected ? '#1d4ed8' : '#6b7280' }};">Año</span>
                    <select
                        wire:model.live="selectedYear"
                        style="height: 1.85rem; padding: 0 1.75rem 0 0.55rem; border-radius: 0.35rem; font-size: 0.8rem; cursor: pointer; {{ $yearSelectStyle }}"
                    >
                        @foreach ($years as $year)
                            <option
                                value="{{ $year }}"
                                style="{{ (int) $year === $currentYear ? 'color:#1d4ed8;font-weight:800;' : 'color:#6b7280;font-weight:600;' }}"
                            >
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($periodLabel)
                <p style="margin: 0; font-size: 0.75rem; color: #6b7280; padding-left: 0.2rem;">
                    Mostrando: <strong style="color:#111827;">{{ $periodLabel }}</strong>
                    @if ($hasClienteFilter)
                        · Cliente: <strong style="color:#1d4ed8;">{{ $clienteQ }}</strong>
                        @if ($monthsWithActivity !== [])
                            · Actividad en:
                            <strong style="color:#15803d;">
                                @foreach ($monthsWithActivity as $m)
                                    {{ $monthBadges[$m]['full'] ?? $monthBadges[$m]['label'] ?? $m }}@if (! $loop->last), @endif
                                @endforeach
                                {{ $this->selectedYear }}
                            </strong>
                        @else
                            · <span style="color:#b91c1c;">sin actividad en {{ $this->selectedYear }}</span>
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
