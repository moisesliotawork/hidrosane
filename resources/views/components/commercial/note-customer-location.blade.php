@props(['note'])

@php
    $locality = $note['locality'] ?? $note['address_info'] ?? 'Sin localidad';
@endphp

@once
    <style>
        .customer-locality {
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: underline;
            text-align: left;
            color: inherit;
        }

        .customer-locality-toggle {
            font-size: 0.75rem;
            font-weight: 600;
            color: #d97706;
            text-decoration: underline;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
        }
    </style>
@endonce

<div x-data="{ showDetails: false }" class="note-customer-location">
    <h3 class="customer-name dark:text-white" x-show="!showDetails" x-cloak>
        {{ $note['customer'] }}
    </h3>

    <button
        type="button"
        class="customer-locality dark:text-white"
        @click="showDetails = !showDetails"
    >
        {{ $locality }}
    </button>

    <div x-show="showDetails" x-cloak class="note-customer-location-details" style="display: none;">
        <p class="customer-address dark:text-white mt-1">
            {{ $note['full_address'] }}
        </p>

        @isset($phones)
            {{ $phones }}
        @endisset

        <button
            type="button"
            class="customer-locality-toggle mt-1"
            @click="showDetails = false"
        >
            Ocultar
        </button>
    </div>
</div>
