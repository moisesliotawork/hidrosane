@props([
    'note',
    'colorData',
    'canSeePhones' => false,
    'asTelLinks' => false,
])

@php
    $phone1Raw = $note['phone'] ?? null;
    $phone2Raw = $note['secondary_phone'] ?? null;
    $phone1 = $phone1Raw ? preg_replace('/\D+/', '', (string) $phone1Raw) : null;
    $phone2 = $phone2Raw ? preg_replace('/\D+/', '', (string) $phone2Raw) : null;
    $street = trim((string) ($note['primary_address'] ?? ''));
    $addressBadge = $street !== '' ? $street : ($note['full_address'] ?? 'Sin dirección');
    $badgeStyle = 'background-color: ' . $colorData['bg_color'] . '; color: ' . $colorData['text_color'] . ';';
@endphp

@once
    <style>
        .note-reveal-badge {
            box-sizing: border-box;
            width: 7.5rem;
            min-width: 7.5rem;
            min-height: 1.5rem;
            padding: 0.125rem 0.5rem;
            border: 0;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.15;
            text-align: center;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .note-reveal-badge.is-open {
            width: auto;
            max-width: 100%;
            min-height: 2rem;
            padding: 0.35rem 0.75rem;
            border-radius: 0.85rem;
            overflow: visible;
        }

        .note-reveal-phones {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.15rem;
            white-space: nowrap;
        }

        .note-reveal-address {
            white-space: normal;
            text-align: left;
        }
    </style>
@endonce

<div class="flex flex-wrap items-center gap-2 mt-2">
    <button
        type="button"
        class="note-reveal-badge"
        :class="{ 'is-open': reveal === 'phone' }"
        style="{{ $badgeStyle }}"
        title="{{ $canSeePhones ? ($phone1Raw ?: 'Teléfono(s)') : 'Teléfono(s)' }}"
        @if ($canSeePhones)
            @click.stop="setReveal('phone')"
        @endif
    >
        <span x-show="reveal !== 'phone'">Teléfono(s)</span>
        <span class="note-reveal-phones" x-show="reveal === 'phone'" style="display: none;">
            @if ($canSeePhones && $asTelLinks && $phone1)
                <a href="tel:{{ $phone1 }}" style="color: inherit; text-decoration: none;" @click.stop>{{ $phone1Raw }}</a>
                @if ($phone2)
                    <a href="tel:{{ $phone2 }}" style="color: inherit; text-decoration: none;" @click.stop>{{ $phone2Raw }}</a>
                @endif
            @elseif ($canSeePhones)
                <span>{{ $phone1Raw ?: 'No disponible' }}</span>
                @if ($phone2Raw)
                    <span>{{ $phone2Raw }}</span>
                @endif
            @else
                Teléfono(s)
            @endif
        </span>
    </button>

    <button
        type="button"
        class="note-reveal-badge"
        :class="{ 'is-open': reveal === 'address' }"
        style="{{ $badgeStyle }}"
        title="{{ $note['full_address'] ?? $addressBadge }}"
        @click.stop="setReveal('address')"
    >
        <span x-show="reveal !== 'address'">Dirección</span>
        <span class="note-reveal-address" x-show="reveal === 'address'" style="display: none;">{{ $addressBadge }}</span>
    </button>

    <input type="checkbox" class="note-checkbox" wire:model.live="selectedNotes"
        value="{{ $note['id'] }}" />
</div>
