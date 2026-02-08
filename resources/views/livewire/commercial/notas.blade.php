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
        }
    </style>

    @if(auth()->user()?->hasRole('team_leader') && count($this->tabs) > 0)
        <div class="px-3 mb-2">
            <div class="flex items-center gap-2 overflow-x-auto">
                @foreach($this->tabs as $tab)
                    <a href="{{ request()->fullUrlWithQuery(['activeTab' => $tab['key']]) }}" class="
                                inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-extrabold whitespace-nowrap
                                border transition
                                {{ $tab['active']
                    ? 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 shadow'
                    : 'bg-gray-50 dark:bg-gray-800 border-gray-200/60 dark:border-gray-700/60 hover:bg-gray-100 dark:hover:bg-gray-700'
                                }}
                            ">
                        {{-- iconito (opcional, estilo Filament) --}}
                        @if($tab['icon'] === 'list')
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                            </svg>
                        @elseif($tab['icon'] === 'user')
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a8.25 8.25 0 0115 0" />
                            </svg>
                        @endif

                        <span class="text-gray-900 dark:text-white">
                            {{ $tab['label'] }}
                        </span>

                        <span class="ml-1 text-xs font-black px-2 py-0.5 rounded-full
                                {{ $tab['active'] ? 'bg-lime-400 text-black' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white' }}
                            ">
                            {{ $tab['badge'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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

            <button class="filament-bulk-btn btn-reten" wire:click="bulkEnviarAReten" wire:loading.attr="disabled"
                wire:target="bulkEnviarAReten" @disabled(count($selectedNotes) === 0)>
                <svg class="heroicon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 3l8 4v6c0 5-3.5 9.5-8 11-4.5-1.5-8-6-8-11V7l8-4z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5m0 3h.01" />
                </svg>
                Enviar a Retén
            </button>
        </div>

        {{-- DERECHA (nuevo botón) --}}
        <div class="bulk-right">
            <a href="{{ \App\Filament\Commercial\Resources\AutogenerarNoteResource::getUrl('create') }}"
                class="filament-bulk-btn btn-autonota">
                {{-- heroicon-o-plus-circle (igual al headerAction) --}}
                <svg class="heroicon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Autogenerar nota
            </a>
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
                            class="absolute -top-1 -right-1 min-w-[1.2rem] h-[1.2rem] px-1 rounded-full bg-lime-500 text-black text-xs font-black flex items-center justify-center">
                            {{ $this->activeFiltersCount }}
                        </span>
                    @endif
                </button>

                {{-- dropdown --}}
                <div x-show="open" x-cloak @click.outside="open=false"
                    class="absolute right-0 mt-2 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden z-50"
                    style="width: 300px; min-width: 300px;">


                    <div class="px-4 py-3 flex items-center justify-between">
                        <div class="text-sm font-extrabold text-gray-900 dark:text-white">Filtros</div>

                        <button type="button" wire:click="resetFilters" @click="open=false"
                            class="text-sm font-bold text-red-400 hover:text-red-300 transition">
                            Resetear los filtros
                        </button>
                    </div>

                    <div class="px-4 pb-4">
                        <div class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">Estado</div>

                        <select wire:model.live="statusFilter"
                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-semibold px-3 py-2 outline-none">
                            <option value="">Todos</option>
                            @foreach($this->statusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
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

                        <!-- Botones de acción -->
                        <div class="action-buttons-container">
                            <button class="action-button" wire:click="toggleDeCamino({{ $note['id'] }})">
                                De Camino
                            </button>
                            <button class="action-button" onclick="getUbicacion({{ $note['id'] }})">
                                GPS
                            </button>
                            <button class="action-button" onclick="getUbicacionDentro({{ $note['id'] }})">
                                Dentro
                            </button>
                            <button class="action-button"
                                onclick="llevarme({{ $note['id'] }}, {{ $note['lat'] ?? 'null' }}, {{ $note['lng'] ?? 'null' }})">
                                Llévame
                            </button>
                        </div>

                        <!-- Nuevo botón que ocupa todo el ancho -->
                        <div class="mt-1"> <!-- Margen superior pequeño para separar -->
                            <button class="action-button w-full" wire:click="redirigirAVenta({{ $note['id'] }})">
                                <!-- w-full para que ocupe todo el ancho -->
                                Gestionar
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
    <script>
        function getUbicacion(notaId) {
            if (location.protocol !== 'https:')
            {
                // Si no está en HTTPS, guardar ubicación fija (Caracas)
                Livewire.dispatch('guardarUbicacion', {
                    notaId: notaId,
                    lat: 10.4806,
                    lng: -66.9036
                });
                alert('Estás en entorno local, se usó ubicación de Caracas.');
                return;
            }

            if (navigator.geolocation)
            {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        Livewire.dispatch('guardarUbicacion', {
                            notaId: notaId,
                            lat: lat,
                            lng: lng
                        });
                    },
                    function (error) {
                        // Si hay error, usar Caracas como fallback
                        Livewire.dispatch('guardarUbicacion', {
                            notaId: notaId,
                            lat: 10.4806,
                            lng: -66.9036
                        });
                        alert('No se pudo obtener ubicación, se usó Caracas. Error: ' + error.message);
                    }
                );
            } else
            {
                // Geolocalización no soportada
                Livewire.dispatch('guardarUbicacion', {
                    notaId: notaId,
                    lat: 10.4806,
                    lng: -66.9036
                });
                alert('Geolocalización no soportada, se usó Caracas.');
            }
        }

        function getUbicacionDentro(notaId) {
            if (location.protocol !== 'https:')
            {
                Livewire.dispatch('guardarUbicacionDentro', {
                    notaId: notaId,
                    lat: 10.4806,   // Caracas
                    lng: -66.9036
                });
                alert('Entorno no HTTPS, se usó ubicación de Caracas (DENTRO).');
                return;
            }

            if (navigator.geolocation)
            {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        Livewire.dispatch('guardarUbicacionDentro', {
                            notaId: notaId,
                            lat: lat,
                            lng: lng
                        });
                    },
                    function (error) {
                        Livewire.dispatch('guardarUbicacionDentro', {
                            notaId: notaId,
                            lat: 10.4806,
                            lng: -66.9036
                        });
                        alert('No se pudo obtener ubicación (DENTRO), se usó Caracas. Error: ' + error.message);
                    }
                );
            } else
            {
                Livewire.dispatch('guardarUbicacionDentro', {
                    notaId: notaId,
                    lat: 10.4806,
                    lng: -66.9036
                });
                alert('Geolocalización no soportada (DENTRO), se usó Caracas.');
            }
        }

        function llevarme(notaId, lat, lng) {
            // ¿Tenemos coordenadas DENTRO?
            if (!lat || !lng)
            {
                // Notificar desde backend con Filament Notifications
                Livewire.dispatch('avisarSinDentro', { notaId: notaId });
                return;
            }

            // 1) Intento abrir app nativa (Android/iOS) con esquema geo:
            //    geo:lat,lng?q=lat,lng (funciona muy bien en móviles)
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isMobile)
            {
                const geoUrl = `geo:${lat},${lng}?q=${lat},${lng}`;
                // Abrimos la app si está disponible
                window.location.href = geoUrl;

                // Fallback a web (si no hay app)
                setTimeout(() => {
                    const webUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                    window.open(webUrl, '_blank');
                }, 600);
                return;
            }

            // 2) En desktop (o si no quieres esquema geo), abre Google Maps Web directamente:
            const webUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            window.open(webUrl, '_blank');
        }
    </script>

</div>