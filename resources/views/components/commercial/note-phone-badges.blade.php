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
    $badgeStyle = 'background-color: ' . $colorData['bg_color'] . '; color: ' . $colorData['text_color'] . '; cursor: pointer;';
@endphp

{{-- Siempre visibles junto a Horario. Badge "Ver" → clic revela (30s y se oculta). Excluyentes. --}}
@if ($canSeePhones)
    <div class="flex flex-col items-center">
        <span class="text-xs text-gray-500 dark:text-gray-400">Teléfono</span>
        <button
            type="button"
            class="text-xs font-semibold px-2 py-0.5 rounded-full"
            style="{{ $badgeStyle }}"
            @click.stop="setReveal('phone')"
        >
            <span x-show="reveal !== 'phone'">Ver</span>
            <span x-show="reveal === 'phone'" style="display: none;">
                @if ($asTelLinks && $phone1)
                    <a href="tel:{{ $phone1 }}" style="color: inherit; text-decoration: none;" @click.stop>{{ $phone1Raw }}</a>
                @else
                    {{ $phone1Raw ?: 'No disponible' }}
                @endif
            </span>
        </button>
    </div>

    @if (filled($phone2Raw))
        <div class="flex flex-col items-center" x-show="reveal === 'phone'" style="display: none;">
            <span class="text-xs text-gray-500 dark:text-gray-400">Teléfono 2</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="{{ $badgeStyle }}">
                @if ($asTelLinks && $phone2)
                    <a href="tel:{{ $phone2 }}" style="color: inherit; text-decoration: none;">{{ $phone2Raw }}</a>
                @else
                    {{ $phone2Raw }}
                @endif
            </span>
        </div>
    @endif
@endif

<div class="flex flex-col items-center min-w-0 max-w-[10rem] sm:max-w-[14rem]">
    <span class="text-xs text-gray-500 dark:text-gray-400">Dirección</span>
    <button
        type="button"
        class="text-xs font-semibold px-2 py-0.5 rounded-full text-center leading-snug"
        style="{{ $badgeStyle }}"
        title="{{ $note['full_address'] ?? $addressBadge }}"
        @click.stop="setReveal('address')"
    >
        <span x-show="reveal !== 'address'">Ver</span>
        <span x-show="reveal === 'address'" style="display: none;">{{ $addressBadge }}</span>
    </button>
</div>
