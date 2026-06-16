<x-filament::page>
    <style>
        .reasignar-visitas-compact .fi-ta-header-cell {
            padding: 0.25rem 0.5rem !important;
        }

        .reasignar-visitas-compact .fi-ta-text {
            padding: 0.1rem 0.5rem !important;
            gap: 0 !important;
        }

        .reasignar-visitas-compact .fi-ta-actions-cell > div {
            padding: 0.1rem 0.5rem !important;
        }

        .reasignar-visitas-compact .fi-ta-text-item-label {
            line-height: 1.1 !important;
        }

        .reasignar-visitas-compact .reasignar-visitas-nombre .fi-ta-text-item-label {
            color: #14532d !important;
            font-weight: 700 !important;
        }

        html.dark .reasignar-visitas-compact .reasignar-visitas-nombre .fi-ta-text-item-label {
            color: #86efac !important;
        }

        .reasignar-visitas-compact .fi-ta-actions .fi-btn {
            min-height: 2.25rem;
            padding: 0.5rem 1.1rem;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.15;
        }

        .reasignar-visitas-compact .fi-ta-header-toolbar {
            gap: 0.25rem;
            padding-block: 0.15rem;
        }

        .reasignar-visitas-compact .fi-ta-pagination {
            padding-block: 0.2rem;
        }

        .reasignar-visitas-compact .fi-input-wrp {
            min-height: 1.65rem;
        }
    </style>

    <div class="reasignar-visitas-compact">
        {{ $this->table }}
    </div>
</x-filament::page>
