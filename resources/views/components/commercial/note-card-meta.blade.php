@props([
    'note',
    'colorData',
    'fechaLabel' => 'Fecha',
])

@php
    $pill = 'text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap';
    $orange = 'background-color: ' . $colorData['bg_color'] . '; color: ' . $colorData['text_color'] . ';';
    $nro = 'background-color: #ec4899; color: #ffffff;';
@endphp

<div class="flex flex-nowrap items-end gap-2 overflow-x-auto mb-3">
    <div class="flex flex-col items-center shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $fechaLabel }}</span>
        <span class="{{ $pill }}" style="{{ $orange }}">{{ $note['visit_date'] }}</span>
    </div>
    <div class="flex flex-col items-center shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">Horario</span>
        <span class="{{ $pill }}" style="{{ $orange }}">{{ $note['visit_schedule'] ?? '--:--' }}</span>
    </div>
    <div class="flex flex-col items-center shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">Nro Nota</span>
        <span class="{{ $pill }}" style="{{ $nro }}">{{ $note['nro_nota'] }}</span>
    </div>
    <div class="flex flex-col items-center shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">Ptos</span>
        <span class="{{ $pill }}" style="{{ $orange }}">{{ $note['fuente_puntaje'] }} pts</span>
    </div>
    <div class="flex flex-col items-center shrink-0">
        <span class="text-xs text-gray-500 dark:text-gray-400">Comercial</span>
        <span class="{{ $pill }}" style="{{ $orange }}">{{ $note['comercial'] }}</span>
    </div>
</div>
