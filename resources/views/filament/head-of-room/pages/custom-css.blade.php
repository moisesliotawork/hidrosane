<style>
    /* 1. Forzar el color azul en cualquier texto dentro de la cabecera del grupo */
    .fi-ta-group-header-row dt, 
    .fi-ta-group-header-row dd, 
    .fi-ta-group-header-row span,
    [data-table-group-header-row] * {
        color: #3b82f6 !important; /* Azul Filament */
        font-weight: 800 !important;
        text-transform: uppercase !important;
    }

    /* 2. Quitar el prefijo "Comercial:" */
    .fi-ta-group-header-row dt,
    [data-table-group-header-row] dt {
        display: none !important;
    }

    /* 3. Darle un fondo sutil para que destaque como una sección */
    .fi-ta-group-header-row {
        background-color: rgba(59, 130, 246, 0.1) !important;
        border-left: 4px solid #3b82f6 !important;
    }
</style>