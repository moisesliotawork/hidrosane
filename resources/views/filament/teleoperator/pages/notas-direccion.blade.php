<x-filament-panels::page>
    <style>
        /* Filament pone px-3 py-4 en .fi-ta-text (las celdas td van con p-0). */
        .notas-direccion-compact .fi-ta-text {
            padding-top: 0.2rem !important;
            padding-bottom: 0.2rem !important;
            gap: 0 !important;
        }

        .notas-direccion-compact .fi-ta-header-cell {
            padding-top: 0.35rem !important;
            padding-bottom: 0.35rem !important;
        }

        .notas-direccion-compact .fi-badge {
            --tw-ring-offset-width: 0 !important;
            padding-top: 0.05rem !important;
            padding-bottom: 0.05rem !important;
            min-height: 0 !important;
            line-height: 1.15 !important;
            font-size: 0.75rem !important;
        }

        .notas-direccion-compact .fi-ta-text-item-label {
            line-height: 1.15 !important;
        }

        .notas-direccion-compact .fi-ta-row {
            line-height: 1.15;
        }

        /* Empaqueta columnas a la izquierda (sin hueco enorme antes de CP/Ciudad). */
        .notas-direccion-compact .fi-ta-table {
            width: max-content;
            max-width: 100%;
        }
    </style>

    <div class="notas-direccion-compact">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
