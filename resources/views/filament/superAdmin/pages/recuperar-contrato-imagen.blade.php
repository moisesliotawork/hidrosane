<x-filament-panels::page>
    <style>
        .recovery-field-label,
        .recovery-datos-infolist .fi-in-entry-wrp-label,
        .recovery-datos-entry-wrp .fi-in-entry-wrp-label {
            font-weight: 800 !important;
            text-decoration: underline !important;
            text-underline-offset: 2px;
            white-space: nowrap !important;
            color: #111827;
        }
        html.dark .recovery-field-label,
        html.dark .recovery-datos-infolist .fi-in-entry-wrp-label,
        html.dark .recovery-datos-entry-wrp .fi-in-entry-wrp-label {
            color: #f3f4f6;
        }
        /* Título y valor siempre en la misma fila */
        .recovery-datos-infolist .fi-fo-component-ctn,
        .recovery-datos-section .fi-fo-component-ctn {
            gap: 0.4rem 0.75rem !important;
        }
        .recovery-datos-entry-wrp > div {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 0.4rem 0.55rem !important;
            grid-template-columns: none !important;
        }
        .recovery-datos-entry-wrp > div > div:first-child {
            flex: 0 0 auto !important;
            min-width: max-content;
        }
        .recovery-datos-entry-wrp > div > div:last-child,
        .recovery-datos-entry-wrp .sm\:col-span-2 {
            flex: 1 1 auto !important;
            min-width: 0;
            grid-column: auto !important;
        }
        .recovery-datos-section {
            padding: 0.5rem 0.75rem !important;
        }
        .recovery-datos-entry {
            white-space: nowrap;
        }
        /* Cabecera VER DATOS: nombre, DNI, fechas — azul, negrita, mayúsculas, sin badge */
        .recovery-datos-highlight-section {
            margin-bottom: 0.75rem !important;
            max-width: 42rem;
        }
        .recovery-datos-highlight-label {
            font-weight: 800 !important;
            text-transform: uppercase !important;
            color: #1d4ed8 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
        }
        html.dark .recovery-datos-highlight-label {
            color: #60a5fa !important;
        }
        .recovery-datos-highlight,
        .recovery-datos-highlight .fi-in-text-item,
        .recovery-datos-highlight-wrp .fi-in-text-item {
            font-weight: 800 !important;
            text-transform: uppercase !important;
            color: #1d4ed8 !important;
        }
        html.dark .recovery-datos-highlight,
        html.dark .recovery-datos-highlight .fi-in-text-item,
        html.dark .recovery-datos-highlight-wrp .fi-in-text-item {
            color: #60a5fa !important;
        }
        .recovery-datos-highlight-form {
            margin-bottom: 0.75rem;
            max-width: none !important;
        }
        .recovery-datos-form-4col .fi-fo-field-wrp {
            gap: 0.35rem 0.5rem;
        }
        /* Etiquetas al lado del valor */
        .recovery-datos-form-4col .fi-fo-field-wrp:not(.fi-fo-textarea) > .grid {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }
        .recovery-datos-highlight-form .fi-fo-field-wrp-label label,
        .recovery-datos-highlight-form label {
            font-weight: 800 !important;
            text-transform: uppercase !important;
            color: #1d4ed8 !important;
            white-space: nowrap;
        }
        html.dark .recovery-datos-highlight-form .fi-fo-field-wrp-label label,
        html.dark .recovery-datos-highlight-form label {
            color: #60a5fa !important;
        }
        .recovery-nro-contrato-input {
            font-size: 1.65rem !important;
            font-weight: 900 !important;
            line-height: 1.2 !important;
            color: #111827 !important;
        }
        html.dark .recovery-nro-contrato-input {
            color: #f9fafb !important;
        }
        .recovery-cliente-nombre-input {
            font-weight: 800 !important;
            text-transform: uppercase !important;
            color: #1d4ed8 !important;
            font-size: 1.05rem !important;
        }
        html.dark .recovery-cliente-nombre-input {
            color: #60a5fa !important;
        }
        .recovery-fecha-verde-input {
            font-weight: 800 !important;
            color: #14532d !important;
        }
        html.dark .recovery-fecha-verde-input {
            color: #86efac !important;
        }
        .recovery-fecha-bold-input {
            font-weight: 800 !important;
        }
        .recovery-dni-input {
            font-weight: 700 !important;
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
        }
        .recovery-iban-input {
            font-weight: 800 !important;
            letter-spacing: 0.03em;
            font-variant-numeric: tabular-nums;
        }
    </style>

    <div class="space-y-6">
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <strong>Paso 1 · Solo SuperAdmin · recuperar contrato.</strong>
            Este módulo no altera altas comerciales, puerta fría ni repartos.
            Flujo de este paso: subir docs <strong>o dictar por voz</strong> → revisar datos → <strong>Aceptar</strong> (tabla) → <strong>Agregar Contrato</strong>.
            El re-enganche de documentos huérfanos <strong>no</strong> se hace aquí: usa el
            <strong>Paso 2 · Docs huérfanos</strong> cuando el contrato ya esté creado.
        </div>

        @if ($step === 'upload')
            <form wire:submit.prevent="analyzeDocuments" class="space-y-4">
                {{ $this->uploadForm }}
                <x-filament::button type="submit" color="warning" wire:loading.attr="disabled">
                    Analizar documentos
                </x-filament::button>
            </form>

            <div class="relative py-2">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        o
                    </span>
                </div>
            </div>

            <form wire:submit.prevent="processVoiceDictation" class="space-y-4">
                {{ $this->voiceForm }}
                <x-filament::button type="submit" color="info" wire:loading.attr="disabled">
                    Procesar dictado
                </x-filament::button>
            </form>
        @else
            <form wire:submit.prevent="acceptRecovered" class="space-y-4">
                {{ $this->reviewForm }}
                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="submit" color="success">
                        Aceptar (guardar en tabla)
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="cancelReview">
                        Cancelar revisión
                    </x-filament::button>
                </div>
            </form>
        @endif

        <div class="pt-2">
            <h2 class="mb-3 text-base font-bold tracking-wide text-gray-900 dark:text-gray-100">
                RECUPERADOS ACEPTADOS
            </h2>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
