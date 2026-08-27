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
    @endphp

    <style>
        .list-contract-compact .fi-ta-header-cell {
            padding: 0.2rem 0.4rem !important;
            font-size: 0.75rem !important;
        }

        .list-contract-compact .fi-ta-cell {
            padding-block: 0 !important;
        }

        .list-contract-compact .fi-ta-text,
        .list-contract-compact .fi-ta-col-wrp,
        .list-contract-compact .fi-ta-checkbox-cell,
        .list-contract-compact .fi-ta-actions-cell > div {
            padding: 0.05rem 0.4rem !important;
            gap: 0 !important;
            min-height: 0 !important;
        }

        .list-contract-compact .fi-ta-text-item-label {
            line-height: 1.15 !important;
            font-size: 0.8125rem !important;
        }

        .list-contract-compact .fi-badge {
            padding: 0.05rem 0.35rem !important;
            font-size: 0.7rem !important;
            line-height: 1.1 !important;
            min-height: 0 !important;
        }

        .list-contract-compact .fi-ta-actions .fi-btn {
            min-height: 1.5rem !important;
            padding: 0.1rem 0.45rem !important;
            font-size: 0.7rem !important;
            line-height: 1.1 !important;
        }

        .list-contract-compact .fi-ta-header-toolbar {
            gap: 0.25rem;
            padding-block: 0.15rem;
        }

        .list-contract-compact .fi-ta-pagination {
            padding-block: 0.2rem;
        }

        .list-contract-compact input[type='checkbox'] {
            width: 0.9rem;
            height: 0.9rem;
        }

        .list-contract-month-bar {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>

    <div class="flex flex-col gap-y-4 list-contract-compact">
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div class="list-contract-month-bar" style="display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; width: 100%; padding: 0.45rem 0.1rem 0.45rem 0.2rem;">
                @foreach ($monthBadges as $monthNum => $badge)
                    @php
                        $isSelected = ! $showAll && $selectedBadgeMonth === (int) $monthNum;
                        $badgeStyle = $isSelected
                            ? "background:{$badge['text']};color:#ffffff;border:2px solid {$badge['text']};font-weight:900;outline:3px solid {$badge['border']};outline-offset:2px;box-shadow:0 2px 8px rgb(0 0 0 / 0.18);z-index:1;position:relative;"
                            : "background:{$badge['bg']};color:{$badge['text']};border:1px solid {$badge['border']};font-weight:600;opacity:.72;";
                    @endphp
                    <button
                        type="button"
                        wire:click="selectCalendarMonth({{ $monthNum }})"
                        title="Filtrar por {{ $badge['label'] }} {{ $this->selectedYear }}"
                        style="flex: 0 0 auto; height: 1.85rem; padding: 0 0.55rem; border-radius: 999px; font-size: 0.68rem; letter-spacing: 0.01em; cursor: pointer; line-height: 1; white-space: nowrap; {{ $badgeStyle }}"
                    >
                        {{ $badge['label'] }}
                    </button>
                @endforeach

                <button
                    type="button"
                    wire:click="showAllPayments"
                    style="flex: 0 0 auto; height: 1.85rem; padding: 0 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer; white-space: nowrap; {{ $tabStyle($showAll) }}"
                >
                    Todos
                </button>

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
                </p>
            @endif
        </div>

        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
