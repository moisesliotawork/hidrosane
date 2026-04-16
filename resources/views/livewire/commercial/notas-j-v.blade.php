<div>
    <style>
        /* Estilos para la información del cliente en TODOS los tamaños */
        .customer-name {
            font-size: 0.875rem;
            line-height: 1.1;
            font-weight: 600;
        }

        .note-checkbox {
            width: 1.1rem;
            height: 1.1rem;
            cursor: pointer;
        }

        .customer-address {
            font-size: 0.75rem;
            line-height: 1;
            font-weight: 600;
        }

        /* Estilos para los botones de acción */
        .action-button {
            flex: 1;
            /* Ocupa espacio igualitario */
            padding: 0.4rem 0.2rem;
            font-size: 0.7rem;
            border-radius: 0.25rem;
            background-color: #4b5563;
            color: #ffffff;
            border: none;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            white-space: nowrap;
            margin: 0 0.1rem;
        }

        .action-button:hover {
            background-color: #d1d5db;
            /* Gris medio al hover */
        }

        .action-buttons-container {
            display: flex;
            gap: 0.2rem;
            margin-top: 0.5rem;
        }

        .action-button.w-full {
            width: 100%;
            margin: 0;
            padding: 0.4rem 0;
        }

        .action-button.w-full {
            flex: 1;
            /* Ocupa espacio igualitario */
            padding: 0.4rem 0.2rem;
            font-size: 0.7rem;
            border-radius: 0.25rem;
            background-color: #00248c;
            color: #ffffff;
            border: none;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            white-space: nowrap;
            margin: 0 0.1rem;
        }

        .customer-phone {
            font-size: 0.75rem;
            line-height: 1.1;
            font-weight: 600;
            color: #000000;
            /* Color negro para modo claro */
            margin-top: 0.1rem;
        }

        .dark .customer-phone {
            color: #ffffff;
            /* Color blanco para modo oscuro */
        }

        .bulk-bar {
            display: flex;
            gap: .5rem;
            margin: .25rem .75rem .75rem .75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filament-bulk-btn {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .75rem;
            border-radius: .6rem;
            font-weight: 700;
            font-size: .78rem;
            line-height: 1;
            color: white;
            border: 0;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .10);
            transition: transform .08s ease, opacity .15s ease;
        }

        .filament-bulk-btn:active {
            transform: scale(.98);
        }

        .filament-bulk-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .btn-sala {
            background: #ec4899;
        }

        /* pink (como tu bulkAction) */
        .btn-reten {
            background: #f59e0b;
        }

        /* warning/amber */
        .bulk-count {
            font-size: .75rem;
            font-weight: 700;
            padding: .25rem .5rem;
            border-radius: 999px;
            background: rgba(107, 114, 128, .15);
        }

        .bulk-left {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .bulk-right {
            margin-left: auto;
            /* empuja a la derecha */
            display: flex;
            align-items: center;
        }

        /* Botón verde estilo Filament bulk */
        .btn-autonota {
            background: #22c55e;
            /* green-500 */
        }

        .btn-autonota:hover {
            filter: brightness(0.95);
        }

        .heroicon {
            width: 1rem;
            height: 1rem;
            display: inline-block;
        }

        .search-bar-wrap {
            margin: 0 .75rem .75rem .75rem;
        }

        .search-bar {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: .75rem;
            width: 1rem;
            height: 1rem;
            color: rgba(107, 114, 128, 1);
            /* gray-500 */
            pointer-events: none;
        }

        .dark .search-icon {
            color: rgba(156, 163, 175, 1);
            /* gray-400 */
        }

        .search-input {
            width: 100%;
            padding: .55rem .75rem .55rem 2.25rem;
            /* espacio para la lupa */
            border-radius: .75rem;
            border: 1px solid rgba(229, 231, 235, 1);
            /* gray-200 */
            background: white;
            font-size: .85rem;
            font-weight: 600;
            outline: none;
        }

        .dark .search-input {
            background: rgba(31, 41, 55, 1);
            /* gray-800 */
            border-color: rgba(55, 65, 81, 1);
            /* gray-700 */
            color: white;
        }

        .search-input:focus {
            border-color: rgba(0, 36, 140, .45);
            box-shadow: 0 0 0 3px rgba(0, 36, 140, .15);
        }

        .action-button {
            flex: 1;
            padding: 0.48rem 0.35rem;
            /* un poco más alto */
            font-size: 0.82rem;
            /* más grande */
            font-weight: 800;
            /* bold fuerte */
            line-height: 1;
            border-radius: 0.6rem;
            /* más “Filament” */
            color: #ffffff;
            /* letras blancas */
            border: 0;
            cursor: pointer;
            text-align: center;
            transition: transform .08s ease, filter .15s ease, opacity .15s ease;
            white-space: nowrap;
            margin: 0 0.1rem;
        }

        .action-button:active {
            transform: scale(.98);
        }

        .action-button:hover {
            filter: brightness(0.95);
        }

        /* Variantes de color */
        .action-edit {
            background: #f59e0b;
            /* amber-500 (amarillo) */
        }

        .action-delete {
            background: #ef4444;
            /* red-500 (rojo) */
        }

        .action-reassign {
            background: #22c55e;
            /* green-500 (verde) */
        }

        /* === FILTROS estilo Filament (unificado) === */
        .filter-control {
            width: 100%;
            border-radius: 1rem;
            border: 1px solid rgba(55, 65, 81, 1);
            background-color: rgba(31, 41, 55, 1);
            color: #fff;
            font-weight: 700;
            font-size: .85rem;
            padding: .75rem 2.6rem .75rem 1rem;
            outline: none;

            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;

            background-image: none !important;
        }

        .filter-control::placeholder {
            color: rgba(156, 163, 175, 1);
        }

        .filter-control:focus {
            border-color: rgba(0, 36, 140, .6);
            box-shadow: 0 0 0 3px rgba(0, 36, 140, .25);
        }

        /* flecha custom SOLO para selects */
        select.filter-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='3'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1.15rem 1.15rem !important;
        }

        .tabs-wrap {
            margin: .25rem .75rem .75rem .75rem;
            display: flex;
            justify-content: center;
        }

        .tabs-pill {
            display: flex;
            gap: .35rem;
            padding: .4rem;
            border-radius: 999px;
            background: rgba(17, 24, 39, .55);
            border: 1px solid rgba(55, 65, 81, .6);
            backdrop-filter: blur(8px);
        }

        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .75rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: .9rem;
            color: rgba(229, 231, 235, .85);
            background: transparent;
            border: 0;
            cursor: pointer;
            transition: background .15s ease, transform .08s ease, color .15s ease;
        }

        .tab-btn:active {
            transform: scale(.98);
        }

        .tab-btn.is-active {
            background: rgba(31, 41, 55, .85);
            color: #38bdf8;
        }

        .tab-ico {
            opacity: .9;
        }

        .tab-badge {
            min-width: 1.6rem;
            height: 1.3rem;
            padding: 0 .45rem;
            border-radius: 999px;
            background: rgba(31, 41, 55, .95);
            border: 1px solid rgba(55, 65, 81, .7);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            font-weight: 900;
            color: rgba(229, 231, 235, .9);
        }

        .tab-btn.is-active .tab-badge {
            background: rgba(16, 185, 129, .12);
            border-color: rgba(163, 230, 53, .25);
            color: #83c51aff;
        }

        @media (max-width: 520px) {
            .tabs-wrap {
                margin: .25rem .5rem .6rem .5rem;
            }

            .tab-btn {
                font-size: .82rem;
                padding: .40rem .6rem;
            }
        }


        @media (max-width: 520px) {
            .bulk-bar {
                flex-wrap: nowrap;
                /* CLAVE: no bajar a otra línea */
                gap: .35rem;
                margin: .25rem .5rem .5rem .5rem;
                overflow-x: auto;
                /* si aun así no cabe, hace scroll horizontal suave */
                -webkit-overflow-scrolling: touch;
            }

            .bulk-left {
                flex-wrap: nowrap;
                /* todo en una fila */
                gap: .35rem;
            }

            .bulk-count {
                font-size: .68rem;
                padding: .18rem .4rem;
                white-space: nowrap;
            }

            .filament-bulk-btn {
                padding: .35rem .55rem;
                border-radius: .55rem;
                font-size: .70rem;
                gap: .35rem;
                white-space: nowrap;
            }

            .heroicon {
                width: .9rem;
                height: .9rem;
            }

            /* Botón verde igual pero compacto */
            .btn-autonota {
                padding: .35rem .55rem;
            }

            .filter-control {
                font-size: .78rem;
                padding: .60rem 2.25rem .60rem .85rem;
                border-radius: .85rem;
            }

            select.filter-control {
                background-position: right .75rem center !important;
                background-size: 1.05rem 1.05rem !important;
            }
        }

        /* Estilos base para móviles (hasta 410px) */
        @media (max-width: 410px) {
            .mobile-optimized {
                font-size: 0.8rem;
            }

            .mobile-optimized .text-xs {
                font-size: 0.7rem;
            }

            .mobile-optimized .text-sm {
                font-size: 0.75rem;
            }

            .mobile-optimized .text-base {
                font-size: 0.85rem;
            }

            .mobile-optimized .p-4 {
                padding: 0.75rem;
            }

            .mobile-optimized .gap-2 {
                gap: 0.5rem;
            }

            .mobile-optimized .rounded-lg {
                border-radius: 0.5rem;
            }

            .mobile-optimized .space-y-4>*+* {
                margin-top: 1rem;
            }

            .bulk-bar {
                flex-wrap: nowrap;
                /* CLAVE: no bajar a otra línea */
                gap: .35rem;
                margin: .25rem .5rem .5rem .5rem;
                overflow-x: auto;
                /* si aun así no cabe, hace scroll horizontal suave */
                -webkit-overflow-scrolling: touch;
            }

            .bulk-left {
                flex-wrap: nowrap;
                /* todo en una fila */
                gap: .35rem;
            }

            .bulk-count {
                font-size: .68rem;
                padding: .18rem .4rem;
                white-space: nowrap;
            }

            .filament-bulk-btn {
                padding: .35rem .55rem;
                border-radius: .55rem;
                font-size: .70rem;
                gap: .35rem;
                white-space: nowrap;
            }

            .heroicon {
                width: .9rem;
                height: .9rem;
            }

            /* Botón verde igual pero compacto */
            .btn-autonota {
                padding: .35rem .55rem;
            }

            .filter-control {
                font-size: .74rem;
                padding: .55rem 2.10rem .55rem .80rem;
                border-radius: .80rem;
            }

            select.filter-control {
                background-position: right .65rem center !important;
                background-size: .98rem .98rem !important;
            }
        }

        /* Ajustes para pantallas ≤385px */
        @media (max-width: 385px) {
            .mobile-optimized {
                font-size: 0.75rem;
            }

            .mobile-optimized .text-xs {
                font-size: 0.65rem;
            }

            .mobile-optimized .text-sm {
                font-size: 0.7rem;
            }

            .mobile-optimized .text-base {
                font-size: 0.8rem;
            }

            .mobile-optimized .p-4 {
                padding: 0.6rem;
            }

            .mobile-optimized .gap-2 {
                gap: 0.35rem;
            }

            .mobile-optimized .px-2 {
                padding-left: 0.3rem;
                padding-right: 0.3rem;
            }

            .mobile-optimized .py-0\.5 {
                padding-top: 0.15rem;
                padding-bottom: 0.15rem;
            }

            .action-button {
                font-size: 0.65rem;
                padding: 0.3rem 0.1rem;
            }

            .action-button.w-full {
                padding: 0.3rem 0;
            }

            .bulk-bar {
                gap: .25rem;
                margin: .2rem .4rem .45rem .4rem;
            }

            .bulk-count {
                font-size: .62rem;
                padding: .16rem .35rem;
            }

            .filament-bulk-btn {
                padding: .30rem .45rem;
                font-size: .64rem;
                border-radius: .5rem;
                gap: .28rem;
            }

            .heroicon {
                width: .82rem;
                height: .82rem;
            }

            .action-button {
                font-size: 0.74rem;
                padding: 0.42rem 0.25rem;
                border-radius: 0.55rem;
            }

            .filter-control {
                font-size: .70rem;
                padding: .50rem 2.00rem .50rem .75rem;
                border-radius: .75rem;
            }

            select.filter-control {
                background-position: right .60rem center !important;
                background-size: .92rem .92rem !important;
            }
        }

        /* Ajustes para pantallas ≤375px */
        @media (max-width: 375px) {
            .mobile-optimized {
                font-size: 0.7rem !important;
            }

            .mobile-optimized .text-xs {
                font-size: 0.6rem !important;
            }

            .mobile-optimized .text-sm {
                font-size: 0.65rem !important;
            }

            .mobile-optimized .text-base {
                font-size: 0.75rem !important;
            }

            .mobile-optimized .p-4 {
                padding: 0.5rem !important;
            }

            .mobile-optimized .gap-2 {
                gap: 0.25rem !important;
            }

            .mobile-optimized .px-2 {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }

            .mobile-optimized .py-0\.5 {
                padding-top: 0.125rem !important;
                padding-bottom: 0.125rem !important;
            }

            .mobile-optimized .space-y-4>*+* {
                margin-top: 0.75rem !important;
            }

            .mobile-optimized .mb-3 {
                margin-bottom: 0.5rem !important;
            }

            .mobile-optimized .mt-3 {
                margin-top: 0.5rem !important;
            }

            .mobile-optimized .my-2 {
                margin-top: 0.25rem !important;
                margin-bottom: 0.25rem !important;
            }

            .action-button {
                font-size: 0.6rem !important;
                padding: 0.25rem 0.1rem !important;
            }

            .action-button.w-full {
                padding: 0.25rem 0;
            }

            .filter-control {
                font-size: .68rem;
                padding: .48rem 1.90rem .48rem .70rem;
                border-radius: .72rem;
            }

            select.filter-control {
                background-position: right .55rem center !important;
                background-size: .90rem .90rem !important;
            }
        }
    </style>

    <div class="tabs-wrap">
        <div class="tabs-pill">
            <button type="button" wire:click="setTab('oficina')"
                class="tab-btn {{ $tab === 'oficina' ? 'is-active' : '' }}">
                <span class="tab-ico">🏢</span>
                <span>Oficina</span>
                <span class="tab-badge">{{ $this->tabCounts['oficina'] ?? 0 }}</span>
            </button>

            <button type="button" wire:click="setTab('todas')"
                class="tab-btn {{ $tab === 'todas' ? 'is-active' : '' }}">
                <span class="tab-ico">≡</span>
                <span>Todas</span>
                <span class="tab-badge">{{ $this->tabCounts['todas'] ?? 0 }}</span>
            </button>

            <button type="button" wire:click="setTab('se')" class="tab-btn {{ $tab === 'se' ? 'is-active' : '' }}">
                <span class="tab-ico">?</span>
                <span>S/E</span>
                <span class="tab-badge">{{ $this->tabCounts['se'] ?? 0 }}</span>
            </button>
        </div>
    </div>


    <div class="bulk-bar">
        {{-- IZQUIERDA --}}
        <div class="bulk-left">
            <span class="bulk-count">
                Seleccionadas: {{ count($selectedNotes) }}
            </span>

            <button class="filament-bulk-btn btn-sala" wire:click="bulkEnviarASala" wire:loading.attr="disabled"
                wire:target="bulkEnviarASala" @disabled(count($selectedNotes) === 0)>
                <svg class="heroicon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 21h18M9 8h6m-6 4h6m-6 4h6M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16" />
                </svg>
                Enviar a Oficina
            </button>
        </div>
    </div>


    <div class="search-bar-wrap">
        <div class="flex items-center gap-2">

            {{-- SEARCH --}}
            <div class="search-bar w-1/2">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-4.35-4.35m1.6-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>

                <input type="text" class="search-input" placeholder="Buscar" wire:model.live.debounce.300ms="search" />
            </div>

            {{-- FUNNEL (Filtros) --}}
            <div x-data="{ open:false }" class="relative">
                <button type="button" @click="open = !open"
                    class="relative inline-flex items-center justify-center w-10 h-10 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    {{-- icon funnel --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-700 dark:text-gray-200" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
                    </svg>

                    {{-- badge count --}}
                    @if($this->activeFiltersCount > 0)
                        <span
                            class="absolute -top-1 -right-1 min-w-[1.2rem] h-[1.2rem] px-1 rounded-full bg-sky-500 text-black text-xs font-black flex items-center justify-center">
                            {{ $this->activeFiltersCount }}
                        </span>
                    @endif
                </button>

                {{-- dropdown --}}
                <div x-show="open" x-cloak @click.outside="open=false"
                    class="filters-dropdown absolute right-0 mt-2 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden z-50">



                    {{-- Header --}}
                    <div
                        class="px-4 py-3 flex items-center justify-between border-b border-gray-200 dark:border-gray-700">
                        <div class="text-sm font-extrabold text-gray-900 dark:text-white">Filtros</div>

                        <button type="button" wire:click="resetFilters" @click="open=false"
                            class="text-sm font-bold text-red-400 hover:text-red-300 transition">
                            Resetear los filtros
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-4 py-4 space-y-4">

                        {{-- ESTADO --}}
                        <div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">Estado</div>

                            <select wire:model.live="statusFilter" class="filter-control">
                                <option value="">Todos</option>
                                @foreach($this->statusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ASIGNACIÓN (RANGO) --}}
                        <div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">
                                Asignación (rango)
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Desde</label>
                                    <input type="date" wire:model.live="assignmentStart" class="filter-control" />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Hasta</label>
                                    <input type="date" wire:model.live="assignmentEnd" class="filter-control" />
                                </div>
                            </div>
                        </div>

                        {{-- ASIGNACIÓN (FECHA EXACTA) --}}
                        <div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">
                                Fecha exacta de asignación
                            </div>

                            <input type="date" wire:model.live="assignmentExact" class="filter-control" />
                        </div>

                        {{-- COMERCIAL --}}
                        <div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">
                                Comercial
                            </div>

                            <select wire:model.live="comercialFilterId" class="filter-control">
                                <option value="">Todos</option>
                                @foreach($this->comercialFilterOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- FECHA SALA --}}
                        <div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">
                                Fecha Sala
                            </div>

                            <input type="date" wire:model.live="sentToSalaAt" class="filter-control" />

                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Al usar este filtro, se mostrarán notas en estado <b>SALA</b>.
                            </p>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="overflow-x-auto">

        <div class="mobile-optimized">
            <div class="space-y-4">

                @forelse($this->notes as $note)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div
                            class="flex items-center gap-2 ml-3 w-full justify-end sm:w-auto sm:justify-start sm:basis-auto basis-full">
                            <input type="checkbox" class="note-checkbox" wire:model.live="selectedNotes"
                                value="{{ $note['id'] }}" />
                        </div>
                        <!-- Línea superior con todos los elementos -->
                        <div class="flex items-center justify-between mb-3">
                            @php
                                $colorData = match ($note['fuente_puntaje']) {
                                    4950 => ['bg_color' => '#f67400', 'text_color' => '#ffffff'],
                                    8900 => ['bg_color' => '#166534', 'text_color' => '#ffffff'],
                                    7500 => ['bg_color' => '#1e40af', 'text_color' => '#ffffff'],
                                    default => ['bg_color' => '#6b7280', 'text_color' => '#ffffff'],
                                };
                            @endphp

                            <!-- Grupo izquierdo - Fecha y Hora -->
                            <div class="flex flex-col gap-1">
                                <div class="flex gap-2">
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Fecha</span>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            style="background-color: {{ $colorData['bg_color'] }}; color: {{ $colorData['text_color'] }};">
                                            {{ $note['visit_date'] }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Horario</span>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            style="background-color: {{ $colorData['bg_color'] }}; color: {{ $colorData['text_color'] }};">
                                            {{ $note['visit_schedule'] ?? '--:--' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Grupo central - Nro Nota, Puntaje y Comercial -->
                            <div class="flex flex-col gap-1">
                                <div class="flex gap-2">
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Nro Nota</span>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            style="background-color: #00248c; color: {{ $colorData['text_color'] }};">
                                            {{ $note['nro_nota'] }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Ptos</span>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            style="background-color: {{ $colorData['bg_color'] }}; color: {{ $colorData['text_color'] }};">
                                            {{ $note['fuente_puntaje'] }} pts
                                        </span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Comercial</span>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            style="background-color: {{ $colorData['bg_color'] }}; color: {{ $colorData['text_color'] }};">
                                            {{ $note['comercial'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información del cliente -->
                        <h3 class="customer-name dark:text-white">{{ $note['customer'] }}</h3>
                        <p class="customer-address dark:text-white">
                            {{ $note['full_address'] }}
                        </p>

                        @if($note['show_phone'] || $this->canAlwaysSeePhones())
                            <div class="mt-1">
                                <p class="customer-phone">Tlf 1: {{ $note['phone'] ?? 'No disponible' }}</p>
                                @if($note['secondary_phone'])
                                    <p class="customer-phone">Tlf 2: {{ $note['secondary_phone'] }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="my-2 border-t border-gray-100 dark:border-gray-700"></div>

                        <div class="action-buttons-container">
                            <button class="action-button action-edit" wire:click="editarNota({{ $note['id'] }})">
                                Editar
                            </button>

                            <button class="action-button action-delete" wire:click="confirmarBorrado({{ $note['id'] }})">
                                Borrar
                            </button>

                            <button class="action-button action-reassign"
                                wire:click="confirmarReasignarComercial({{ $note['id'] }})">
                                Reasignar Comercial
                            </button>
                        </div>


                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No hay notas registradas</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    <x-filament::modal id="confirm-delete-note" width="3xl">
        <x-slot name="heading">
            Eliminar nota
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Estás a punto de eliminar esta nota.
            </p>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex gap-3">
                    <div class="mt-0.5 text-warning-600">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                    </div>

                    <div>
                        <div class="font-bold text-gray-900 dark:text-white">
                            Esta acción no se puede deshacer.
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            La nota será eliminada permanentemente del sistema.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2 w-full">
                <x-filament::button color="gray" wire:click="cancelarBorrado">
                    Cancelar
                </x-filament::button>

                <x-filament::button color="danger" wire:click="borrarNotaConfirmada" wire:loading.attr="disabled"
                    wire:target="borrarNotaConfirmada">
                    Sí, eliminar
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="reassign-commercial-note" width="3xl">
        <x-slot name="heading">
            Reasignar comercial
        </x-slot>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex gap-3">
                <div class="mt-0.5 text-warning-600">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                </div>

                <div>
                    <div class="font-bold text-gray-900 dark:text-white">
                        Nota
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        Si la nota está en <strong>SALA</strong>, se reiniciará a <strong>SIN ESTADO</strong> y se
                        limpiará la fecha de oficina.
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Selecciona el comercial y la fecha de asignación. Si dejas el comercial vacío, se removerá la
                asignación.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Comercial --}}
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-gray-900 dark:text-white">
                        Seleccionar Comercial
                    </label>

                    <select wire:model="reassignComercialId"
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-semibold px-3 py-2 outline-none">
                        <option value="">Sin asignar</option>

                        @foreach($this->reassignComercialOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    @error('reassignComercialId')
                        <div class="text-sm text-danger-600">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Fecha --}}
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-gray-900 dark:text-white">
                        Fecha de asignación
                    </label>

                    <input type="date" wire:model="reassignAssignmentDate"
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-semibold px-3 py-2 outline-none" />

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Si se deja vacío, se usará la fecha actual (si hay comercial asignado).
                    </p>

                    @error('reassignAssignmentDate')
                        <div class="text-sm text-danger-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2 w-full">
                <x-filament::button color="gray" wire:click="cancelarReasignarComercial">
                    Cancelar
                </x-filament::button>

                <x-filament::button color="success" wire:click="reasignarComercialConfirmado"
                    wire:loading.attr="disabled" wire:target="reasignarComercialConfirmado">
                    Guardar
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>


</div>
