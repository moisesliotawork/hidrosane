@php
    $notePhone = \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatPhoneDisplay(
        $phoneNote->customer?->phone1_commercial ?: $phoneNote->customer?->phone
    );
    $isExpanded = $expandedNoteId === $phoneNote->id;
    $isSelected = in_array($phoneNote->id, $selectedNoteIds, true);
@endphp

<tr
    wire:key="phone-note-row-{{ $phoneNote->id }}"
    class="{{ $isSelected ? 'bg-success-50 dark:bg-success-950' : ($isExpanded ? 'bg-primary-50 dark:bg-primary-950' : '') }}"
>
    <td class="px-4 py-3">
        <input
            type="checkbox"
            wire:click="toggleSelection({{ $phoneNote->id }})"
            @checked($isSelected)
            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
        />
    </td>
    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
        {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatNroNota($phoneNote->nro_nota) }}
    </td>
    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
        {{ \App\Filament\SuperAdmin\Resources\SuperAsignarResource::formatCustomerName($phoneNote->customer) }}
    </td>
    <td class="px-4 py-3">
        <span class="text-base font-bold tracking-wide text-gray-950 dark:text-white">
            {{ $notePhone ?: '—' }}
        </span>
    </td>
    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
        {{ $phoneNote->created_at?->format('d/m/Y H:i') ?: '—' }}
    </td>
    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
        {{ $phoneNote->assignment_date?->format('d/m/Y') ?: '—' }}
    </td>
    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
        {{ $phoneNote->visit_date?->format('d/m/Y H:i') ?: '—' }}
    </td>
    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
        @if ($phoneNote->reten)
            COMERCIAL RETÉN
        @elseif ($phoneNote->comercial)
            {{ $phoneNote->comercial->empleado_id }}
            {{ trim($phoneNote->comercial->name . ' ' . $phoneNote->comercial->last_name) }}
        @else
            Sin asignar
        @endif
    </td>
    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
        {{ $phoneNote->estado_terminal?->label() ?: 'S/E' }}
    </td>
    <td class="px-4 py-3">
        <x-filament::button
            size="sm"
            color="{{ $isExpanded ? 'gray' : 'primary' }}"
            wire:click="openReassignForm({{ $phoneNote->id }})"
        >
            {{ $isExpanded ? 'Cerrar' : 'Reasignar' }}
        </x-filament::button>
    </td>
</tr>

@if ($isExpanded)
    <tr wire:key="phone-note-form-{{ $phoneNote->id }}">
        <td colspan="10" class="bg-primary-50 px-4 py-4 dark:bg-primary-950">
            @include('filament.superAdmin.resources.super-asignar-resource.partials.reassign-panel', [
                'note' => $phoneNote,
                'assignableOptions' => $assignableOptions,
            ])
        </td>
    </tr>
@endif
