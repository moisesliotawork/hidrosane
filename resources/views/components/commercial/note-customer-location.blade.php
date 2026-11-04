@props([
    'note',
])

@php
    $locality = $note['locality'] ?? $note['address_info'] ?? 'Sin localidad';
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

{{-- Nombre + localidad/CP siempre visibles --}}
<div class="note-customer-location">
    <h3 class="customer-name dark:text-white">
        {{ $note['customer'] }}
    </h3>

    <p class="customer-locality-line customer-address dark:text-white mt-0.5">
        {{ $locality }}
    </p>
</div>
