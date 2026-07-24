<div style="display:flex;flex-wrap:nowrap;gap:6px;align-items:center;margin:4px 0 10px;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;">
    @foreach ($badges as $badge)
        @php
            $isActive = filled($activeDate) && \Carbon\Carbon::parse($activeDate)->toDateString() === $badge['date'];
        @endphp
        <button
            type="button"
            wire:click="selectWeekdayDate('{{ $badge['date'] }}')"
            title="{{ $badge['full'] ?? $badge['label'] }} {{ $badge['short'] }} ({{ $badge['count'] }})"
            style="
                display:inline-flex;
                flex-shrink:0;
                align-items:center;
                gap:4px;
                border:1px solid {{ $isActive ? $badge['text'] : 'transparent' }};
                background:{{ $badge['bg'] }};
                color:{{ $badge['text'] }};
                border-radius:999px;
                padding:3px 8px;
                font-size:11px;
                font-weight:700;
                line-height:1.2;
                cursor:pointer;
                box-shadow:{{ $isActive ? '0 0 0 2px rgba(0,0,0,.06)' : 'none' }};
            "
        >
            <span>{{ $badge['label'] }}</span>
            <span style="opacity:.8;font-weight:600;">{{ $badge['short'] }}</span>
        </button>
    @endforeach

    @if (filled($activeDate))
        <button
            type="button"
            wire:click="clearWeekdayDate"
            style="
                display:inline-flex;
                flex-shrink:0;
                align-items:center;
                border-radius:999px;
                padding:3px 8px;
                font-size:11px;
                font-weight:700;
                background:#f3f4f6;
                color:#4b5563;
                border:1px solid #e5e7eb;
                cursor:pointer;
            "
        >
            Limpiar
        </button>
    @endif
</div>
