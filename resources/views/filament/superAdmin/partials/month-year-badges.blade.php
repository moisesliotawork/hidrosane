@php
    use App\Support\Filament\MonthYearBadgeFilter;

    /** @var string $prefix  Prefijo de propiedades Livewire: var | res | num */
    $prefix = $prefix ?? 'var';
    $props = match ($prefix) {
        'res' => [
            'yearProp' => 'resSelectedYear',
            'yearMonthProp' => 'resSelectedYearMonth',
            'showAllProp' => 'resShowAllMonths',
            'selectMethod' => 'selectResMonth',
            'showAllMethod' => 'showAllResMonths',
        ],
        'num' => [
            'yearProp' => 'numSelectedYear',
            'yearMonthProp' => 'numSelectedYearMonth',
            'showAllProp' => 'numShowAllMonths',
            'selectMethod' => 'selectNumMonth',
            'showAllMethod' => 'showAllNumMonths',
        ],
        'solo' => [
            'yearProp' => 'soloSelectedYear',
            'yearMonthProp' => 'soloSelectedYearMonth',
            'showAllProp' => 'soloShowAllMonths',
            'selectMethod' => 'selectSoloMonth',
            'showAllMethod' => 'showAllSoloMonths',
        ],
        default => [
            'yearProp' => 'varSelectedYear',
            'yearMonthProp' => 'varSelectedYearMonth',
            'showAllProp' => 'varShowAllMonths',
            'selectMethod' => 'selectVarMonth',
            'showAllMethod' => 'showAllVarMonths',
        ],
    };
    $yearProp = $props['yearProp'];
    $yearMonthProp = $props['yearMonthProp'];
    $showAllProp = $props['showAllProp'];
    $selectMethod = $props['selectMethod'];
    $showAllMethod = $props['showAllMethod'];

    $monthBadges = MonthYearBadgeFilter::monthBadges();
    $years = MonthYearBadgeFilter::availableYears();
    $selectedYear = (int) $this->{$yearProp};
    $showAll = (bool) $this->{$showAllProp};
    $selectedYearMonth = $this->{$yearMonthProp};
    $selectedBadgeMonth = null;
    if (! $showAll && filled($selectedYearMonth)) {
        try {
            $selectedBadgeMonth = (int) \Illuminate\Support\Carbon::createFromFormat('Y-m', $selectedYearMonth)->month;
        } catch (\Throwable) {
            $selectedBadgeMonth = null;
        }
    }
    $currentYear = (int) now()->year;
    $isCurrentYearSelected = $selectedYear === $currentYear;
    $yearSelectStyle = $isCurrentYearSelected
        ? 'background:#dbeafe;color:#1d4ed8;border:1px solid #1d4ed8;font-weight:800;'
        : 'background:#f3f4f6;color:#6b7280;border:1px solid #9ca3af;font-weight:700;';
    $tabStyle = static function (bool $active): string {
        return $active
            ? 'background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;font-weight:700;'
            : 'background:#fff;color:#111827;border:1px solid #9ca3af;font-weight:600;';
    };
    $periodLabel = MonthYearBadgeFilter::periodLabel(
        $selectedYearMonth,
        $showAll,
        $allLabel ?? 'Todos'
    );
@endphp

<div style="display: flex; flex-direction: column; gap: 0.45rem; margin-bottom: 0.75rem;">
    <div class="list-contract-month-bar" style="display: flex; flex-wrap: nowrap; gap: 0.28rem; align-items: center; width: 100%; overflow-x: auto; padding: 0.35rem 0.1rem;">
        @foreach ($monthBadges as $monthNum => $badge)
            @php
                $isSelected = ! $showAll && $selectedBadgeMonth === (int) $monthNum;
                $badgeStyle = $isSelected
                    ? "background:{$badge['text']};color:#ffffff;border:2px solid {$badge['text']};font-weight:900;outline:3px solid {$badge['border']};outline-offset:2px;box-shadow:0 2px 8px rgb(0 0 0 / 0.18);z-index:1;position:relative;"
                    : "background:{$badge['bg']};color:{$badge['text']};border:1px solid {$badge['border']};font-weight:600;opacity:.72;";
            @endphp
            <button
                type="button"
                wire:click="{{ $selectMethod }}({{ $monthNum }})"
                title="Filtrar {{ $badge['label'] }} {{ $selectedYear }}"
                style="flex: 0 0 auto; height: 1.85rem; padding: 0 0.55rem; border-radius: 999px; font-size: 0.68rem; letter-spacing: 0.01em; cursor: pointer; line-height: 1; white-space: nowrap; {{ $badgeStyle }}"
            >
                {{ $badge['label'] }}
            </button>
        @endforeach

        <button
            type="button"
            wire:click="{{ $showAllMethod }}"
            style="flex: 0 0 auto; height: 1.85rem; padding: 0 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer; white-space: nowrap; {{ $tabStyle($showAll) }}"
        >
            Todos
        </button>

        <label style="display: inline-flex; align-items: center; gap: 0.35rem; flex: 0 0 auto;">
            <span style="font-size: 0.75rem; font-weight: 600; color: {{ $isCurrentYearSelected ? '#1d4ed8' : '#6b7280' }};">Año</span>
            <select
                wire:model.live="{{ $yearProp }}"
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
        <p style="margin: 0; font-size: 0.75rem; color: #6b7280;">
            Mostrando: <strong style="color:#111827;">{{ $periodLabel }}</strong>
        </p>
    @endif
</div>
