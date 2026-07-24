<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:4px 0 10px;">
    <span style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;margin-right:4px;">
        Días futuros
    </span>

    @foreach ($badges as $badge)
        @php
            $isActive = filled($activeDate) && \Carbon\Carbon::parse($activeDate)->toDateString() === $badge['date'];
        @endphp
        <button
            type="button"
            wire:click="selectWeekdayDate('{{ $badge['date'] }}')"
            style="
                display:inline-flex;
                align-items:center;
                gap:6px;
                border:1px solid {{ $isActive ? $badge['text'] : 'transparent' }};
                background:{{ $badge['bg'] }};
                color:{{ $badge['text'] }};
                border-radius:999px;
                padding:4px 10px;
                font-size:12px;
                font-weight:700;
                line-height:1.2;
                cursor:pointer;
                box-shadow:{{ $isActive ? '0 0 0 2px rgba(0,0,0,.06)' : 'none' }};
            "
            title="Ver asignaciones del {{ $badge['label'] }} {{ $badge['short'] }}"
        >
            <span>{{ $badge['label'] }}</span>
            <span style="opacity:.75;font-weight:600;">{{ $badge['short'] }}</span>
            <span style="
                background:rgba(255,255,255,.55);
                border-radius:999px;
                padding:1px 6px;
                font-size:11px;
                font-weight:800;
            ">{{ $badge['count'] }}</span>
        </button>
    @endforeach

    @if (filled($activeDate))
        <button
            type="button"
            wire:click="clearWeekdayDate"
            style="
                display:inline-flex;
                align-items:center;
                border-radius:999px;
                padding:4px 10px;
                font-size:11px;
                font-weight:700;
                background:#f3f4f6;
                color:#4b5563;
                border:1px solid #e5e7eb;
                cursor:pointer;
            "
        >
            Limpiar fecha
        </button>
    @endif
</div>
