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
    $fullAddress = trim((string) ($note['full_address'] ?? ''));
    if ($fullAddress === '' || $fullAddress === 'Sin dirección') {
        $street = trim((string) ($note['primary_address'] ?? ''));
        $fullAddress = $street !== '' ? $street : 'Sin dirección';
    }
    $customerName = $note['customer'] ?? 'Sin cliente';
    $locality = $note['locality'] ?? $note['address_info'] ?? 'Sin localidad';
    $badgeStyle = 'background-color: ' . $colorData['bg_color'] . '; color: ' . $colorData['text_color'] . '; cursor: pointer;';
    $lockedStyle = 'background-color: #9ca3af; color: #ffffff; cursor: not-allowed; opacity: 0.85;';
@endphp

{{-- Nombre + CP/ciudad --}}
<div class="note-customer-beside-phone w-full min-w-0 text-left" style="flex: 1 1 100%;">
    <h3 class="customer-name dark:text-white">
        {{ $customerName }}
    </h3>
    <p class="customer-locality-line customer-address dark:text-white mt-0.5">
        {{ $locality }}
    </p>
</div>

{{-- Dirección y Teléfono lado a lado --}}
<div class="note-dir-tel-row w-full flex items-end justify-start gap-4 min-w-0" style="flex: 1 1 100%;">
    <div class="flex flex-col items-start flex-shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">Dirección</span>
        <button
            type="button"
            class="text-xs font-semibold px-2 py-0.5 rounded-full"
            style="{{ $badgeStyle }}"
            title="{{ $fullAddress }}"
            x-show="!$store.noteReveal || $store.noteReveal.addressNoteId !== noteId"
            @click.stop="setReveal('address')"
        >
            Ver
        </button>
        <button
            type="button"
            class="text-xs font-semibold px-2 py-0.5 rounded-full"
            style="{{ $badgeStyle }}; display: none;"
            title="Ocultar dirección"
            x-show="$store.noteReveal && $store.noteReveal.addressNoteId === noteId"
            @click.stop="setReveal('address')"
        >
            Ocultar
        </button>
    </div>

    <div class="flex flex-col items-start flex-shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">Teléfono</span>
        @if ($canSeePhones)
            <button
                type="button"
                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                style="{{ $badgeStyle }}"
                x-show="!$store.noteReveal || $store.noteReveal.phoneNoteId !== noteId"
                @click.stop="setReveal('phone')"
            >
                Ver
            </button>
            <button
                type="button"
                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                style="{{ $badgeStyle }}; display: none;"
                title="Ocultar teléfono"
                x-show="$store.noteReveal && $store.noteReveal.phoneNoteId === noteId"
                @click.stop="setReveal('phone')"
            >
                Ocultar
            </button>
        @else
            <button
                type="button"
                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                style="{{ $lockedStyle }}"
                title="Teléfonos bloqueados de momento"
                disabled
                aria-disabled="true"
            >
                Bloqueado
            </button>
        @endif
    </div>
</div>

{{-- Solo lo solicitado: dirección O teléfonos --}}
<div
    class="note-address-block flex flex-col items-stretch min-w-0"
    x-show="$store.noteReveal && $store.noteReveal.addressNoteId === noteId"
    style="display: none;"
>
    <button
        type="button"
        class="note-address-revealed text-left leading-snug whitespace-normal break-words bg-transparent border-0 p-0 m-0 cursor-pointer w-full"
        title="Ocultar dirección"
        @click.stop="setReveal('address')"
    >
        {{ $fullAddress }}
    </button>
</div>

@if ($canSeePhones)
    <div
        class="note-phones-revealed w-full flex flex-col items-start gap-1 min-w-0"
        style="display: none; flex: 1 1 100%;"
        x-show="$store.noteReveal && $store.noteReveal.phoneNoteId === noteId"
    >
        <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="{{ $badgeStyle }}">
            @if ($asTelLinks && $phone1)
                <a href="tel:{{ $phone1 }}" style="color: inherit; text-decoration: none;">{{ $phone1Raw ?: 'No disponible' }}</a>
            @else
                {{ $phone1Raw ?: 'No disponible' }}
            @endif
        </span>
        @if (filled($phone2Raw))
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="{{ $badgeStyle }}">
                @if ($asTelLinks && $phone2)
                    <a href="tel:{{ $phone2 }}" style="color: inherit; text-decoration: none;">{{ $phone2Raw }}</a>
                @else
                    {{ $phone2Raw }}
                @endif
            </span>
        @endif
    </div>
@endif

@once
    <style>
        .note-customer-beside-phone .customer-name {
            font-size: 1.05rem;
            line-height: 1.2;
            font-weight: 800;
            margin: 0;
            text-align: left;
        }
        .note-customer-beside-phone .customer-locality-line {
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.15;
            margin: 0;
            text-align: left;
        }
        .note-dir-tel-row {
            margin-top: 0.35rem;
        }
        .note-address-block {
            flex: 1 1 100%;
            width: 100%;
            max-width: 100%;
            min-width: 100%;
            margin-top: 0.35rem;
        }
        .note-address-revealed {
            display: block;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            font-size: 0.95rem;
            font-weight: 800;
            line-height: 1.3;
            color: #1e3a8a;
            background: transparent !important;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .dark .note-address-revealed {
            color: #93c5fd;
        }
        .note-phones-revealed {
            margin-top: 0.35rem;
        }
    </style>
@endonce
