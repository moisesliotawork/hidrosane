@props([
    'note',
])

@php
    $raw = trim((string) ($note['locality'] ?? $note['address_info'] ?? ''));
    $locality = 'Sin localidad';

    if ($raw !== '') {
        if (preg_match('/^(\d{4,5}),\s*(.+)$/u', $raw, $matches)) {
            $locality = $matches[2] . ', ' . $matches[1];
        } elseif (preg_match('/^(.+),\s*(\d{4,5})$/u', $raw, $matches)) {
            $locality = $matches[1] . ', ' . $matches[2];
        } else {
            $locality = $raw;
        }
    }
@endphp

@once
    <style>
        .customer-locality-line {
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.2;
        }
    </style>
@endonce

<div class="note-customer-location">
    <h3 class="customer-name dark:text-white">
        {{ $note['customer'] }}
    </h3>

    <p class="customer-locality-line customer-address dark:text-white mt-0.5">
        {{ $locality }}
    </p>
</div>
