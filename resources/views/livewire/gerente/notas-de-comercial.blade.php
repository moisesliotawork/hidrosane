<div>
    <style>
        /* Estilos para la información del cliente en TODOS los tamaños */
        .customer-name {
            font-size: 0.875rem;
            line-height: 1.1;
            font-weight: 600;
        }

        .customer-address {
            font-size: 0.75rem;
            line-height: 1;
            font-weight: 600;
        }

        /* Estilos para los botones de acción */
        .action-button {
            flex: 1;
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
            margin-top: 0.1rem;
        }

        .dark .customer-phone {
            color: #ffffff;
        }

        /* Botón verde del mismo estilo */
        .action-button.green {
            background-color: #16a34a;
        }

        /* green-600 */
        .action-button.green:hover {
            background-color: #15803d;
        }

        .phone-buttons-container {
            display: flex;
            justify-content: flex-start;
            /* alineados a la izquierda */
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-top: 0.4rem;
        }

        .phone-button {
            font-size: 0.7rem;
            padding: 0.3rem 0.5rem;
            border-radius: 0.4rem;
            /* más cuadrado */
            background-color: #bfdbfe;
            /* azul claro */
            color: #1e3a8a;
            /* texto azul oscuro */
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.15s ease;
            flex: 0 0 auto;
            /* no se estiran */
        }

        .phone-button:hover {
            background-color: #93c5fd;
            /* azul un poco más intenso al pasar */
        }

        .dark .phone-button {
            color: #0b1120;
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
        }

        /* Ajustes para ≤385px */
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
        }

        /* Ajustes para ≤375px */
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

    <div class="overflow-x-auto mobile-optimized space-y-6">
        {{-- ======= Sección: Notas de HOY ======= --}}
        <x-filament::section heading="Notas de hoy">
            <div class="space-y-4">
                @forelse($this->notesToday as $note)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-3">
                            @php
                                $colorData = match ($note['fuente_puntaje']) {
                                    4950 => ['bg_color' => '#f67400', 'text_color' => '#ffffff'],
                                    8900 => ['bg_color' => '#166534', 'text_color' => '#ffffff'],
                                    7500 => ['bg_color' => '#1e40af', 'text_color' => '#ffffff'],
                                    default => ['bg_color' => '#6b7280', 'text_color' => '#ffffff'],
                                };
                            @endphp

                            <div class="flex flex-col gap-1">
                                <div class="flex gap-2">
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Fecha Visit</span>
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

                        <h3 class="customer-name dark:text-white">{{ $note['customer'] }}</h3>
                        <p class="customer-address dark:text-white">{{ $note['primary_address'] }}</p>
                        <p class="customer-address dark:text-white">{{ $note['address_info'] }}</p>


                        @php
                            $phone1Raw = $note['phone'] ?? null;
                            $phone2Raw = $note['secondary_phone'] ?? null;

                            // Quitamos espacios y caracteres no numéricos para el enlace tel:
                            $phone1 = $phone1Raw ? preg_replace('/\D+/', '', $phone1Raw) : null;
                            $phone2 = $phone2Raw ? preg_replace('/\D+/', '', $phone2Raw) : null;
                        @endphp

                        @if($phone1 || $phone2)
                            <div class="phone-buttons-container">
                                @if($phone1)
                                    <a href="tel:{{ $phone1 }}" class="phone-button">
                                        Tlf 1: {{ $phone1Raw }}
                                    </a>
                                @endif

                                @if($phone2)
                                    <a href="tel:{{ $phone2 }}" class="phone-button">
                                        Tlf 2: {{ $phone2Raw }}
                                    </a>
                                @endif
                            </div>
                        @else
                            <p class="customer-phone mt-1">Teléfono: No disponible</p>
                        @endif

                        <div class="my-2 border-t border-gray-100 dark:border-gray-700"></div>

                        <div class="action-buttons-container">
                            <button class="action-button" wire:click="toggleDeCamino({{ $note['id'] }})">De Camino</button>
                            <button class="action-button" onclick="getUbicacion({{ $note['id'] }})">GPS</button>
                            <button class="action-button" onclick="getUbicacionDentro({{ $note['id'] }})">Dentro</button>
                            <button class="action-button"
                                onclick="llevarme({{ $note['id'] }}, {{ $note['lat'] ?? 'null' }}, {{ $note['lng'] ?? 'null' }})">
                                Llévame
                            </button>
                        </div>

                        <div class="mt-1">
                            <button class="action-button w-full green" wire:click="openReassignModal({{ $note['id'] }})">
                                Reasignar Visita
                            </button>
                        </div>

                        <div class="mt-1">
                            <button class="action-button w-full"
                                wire:click="redirigirAVenta({{ $note['id'] }})">Gestionar</button>
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No hay notas de hoy.</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        {{-- ======= Sección: TODAS las notas ======= --}}
        <x-filament::section heading="Todas las notas">
            <div class="space-y-4">
                @forelse($this->notesAll as $note)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-3">
                            @php
                                $colorData = match ($note['fuente_puntaje']) {
                                    4950 => ['bg_color' => '#f67400', 'text_color' => '#ffffff'],
                                    8900 => ['bg_color' => '#166534', 'text_color' => '#ffffff'],
                                    7500 => ['bg_color' => '#1e40af', 'text_color' => '#ffffff'],
                                    default => ['bg_color' => '#6b7280', 'text_color' => '#ffffff'],
                                };
                            @endphp

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

                        <h3 class="customer-name dark:text-white">{{ $note['customer'] }}</h3>
                        <p class="customer-address dark:text-white">{{ $note['primary_address'] }}</p>
                        <p class="customer-address dark:text-white">{{ $note['address_info'] }}</p>


                        @php
                            $phone1Raw = $note['phone'] ?? null;
                            $phone2Raw = $note['secondary_phone'] ?? null;

                            // Quitamos espacios y caracteres no numéricos para el enlace tel:
                            $phone1 = $phone1Raw ? preg_replace('/\D+/', '', $phone1Raw) : null;
                            $phone2 = $phone2Raw ? preg_replace('/\D+/', '', $phone2Raw) : null;
                        @endphp

                        @if($phone1 || $phone2)
                            <div class="phone-buttons-container">
                                @if($phone1)
                                    <a href="tel:{{ $phone1 }}" class="phone-button">
                                        Tlf 1: {{ $phone1Raw }}
                                    </a>
                                @endif

                                @if($phone2)
                                    <a href="tel:{{ $phone2 }}" class="phone-button">
                                        Tlf 2: {{ $phone2Raw }}
                                    </a>
                                @endif
                            </div>
                        @else
                            <p class="customer-phone mt-1">Teléfono: No disponible</p>
                        @endif


                        <div class="my-2 border-t border-gray-100 dark:border-gray-700"></div>

                        <div class="action-buttons-container">
                            <button class="action-button" wire:click="toggleDeCamino({{ $note['id'] }})">De Camino</button>
                            <button class="action-button" onclick="getUbicacion({{ $note['id'] }})">GPS</button>
                            <button class="action-button" onclick="getUbicacionDentro({{ $note['id'] }})">Dentro</button>
                            <button class="action-button"
                                onclick="llevarme({{ $note['id'] }}, {{ $note['lat'] ?? 'null' }}, {{ $note['lng'] ?? 'null' }})">
                                Llévame
                            </button>
                        </div>

                        <div class="mt-1">
                            <button class="action-button w-full green" wire:click="openReassignModal({{ $note['id'] }})">
                                Reasignar Visita
                            </button>
                        </div>

                        <div class="mt-1">

                            <button class="action-button w-full"
                                wire:click="redirigirAVenta({{ $note['id'] }})">Gestionar</button>
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No hay notas registradas.</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    {{-- ===== Modal de Reasignación ===== --}}
    @if($showReassignModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50"
            wire:keydown.escape="$set('showReassignModal', false)">
            <div class="bg-white dark:bg-gray-900 dark:text-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold mb-4">Reasignar visita (no cambia la fecha)</h3>

                <label class="block text-sm mb-2">Nuevo comercial</label>
                <select wire:model="newComercialId"
                    class="w-full border rounded p-2 bg-white dark:bg-gray-900 dark:text-white">
                    <option value="">-- Elegir comercial --</option>
                    @foreach($this->comerciales as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="mt-6 flex gap-2">
                    <button wire:click="$set('showReassignModal', false)"
                        class="flex-1 px-3 py-2 rounded border border-gray-300 dark:border-gray-700">
                        Cancelar
                    </button>
                    <button wire:click="reassignVisit" class="flex-1 px-3 py-2 rounded text-white"
                        style="background-color:#16a34a">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif


    <script>
        function getUbicacion(notaId) {
            if (location.protocol !== 'https:')
            {
                Livewire.dispatch('guardarUbicacion', { notaId, lat: 10.4806, lng: -66.9036 });
                alert('Estás en entorno local, se usó ubicación de Caracas.');
                return;
            }
            if (navigator.geolocation)
            {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        Livewire.dispatch('guardarUbicacion', { notaId, lat, lng });
                    },
                    function (error) {
                        Livewire.dispatch('guardarUbicacion', { notaId, lat: 10.4806, lng: -66.9036 });
                        alert('No se pudo obtener ubicación, se usó Caracas. Error: ' + error.message);
                    }
                );
            } else
            {
                Livewire.dispatch('guardarUbicacion', { notaId, lat: 10.4806, lng: -66.9036 });
                alert('Geolocalización no soportada, se usó Caracas.');
            }
        }

        function getUbicacionDentro(notaId) {
            if (location.protocol !== 'https:')
            {
                Livewire.dispatch('guardarUbicacionDentro', { notaId, lat: 10.4806, lng: -66.9036 });
                alert('Entorno no HTTPS, se usó ubicación de Caracas (DENTRO).');
                return;
            }
            if (navigator.geolocation)
            {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        Livewire.dispatch('guardarUbicacionDentro', { notaId, lat, lng });
                    },
                    function (error) {
                        Livewire.dispatch('guardarUbicacionDentro', { notaId, lat: 10.4806, lng: -66.9036 });
                        alert('No se pudo obtener ubicación (DENTRO), se usó Caracas. Error: ' + error.message);
                    }
                );
            } else
            {
                Livewire.dispatch('guardarUbicacionDentro', { notaId, lat: 10.4806, lng: -66.9036 });
                alert('Geolocalización no soportada (DENTRO), se usó Caracas.');
            }
        }

        function llevarme(notaId, lat, lng) {
            if (!lat || !lng)
            {
                Livewire.dispatch('avisarSinDentro', { notaId });
                return;
            }
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isMobile)
            {
                const geoUrl = `geo:${lat},${lng}?q=${lat},${lng}`;
                window.location.href = geoUrl;
                setTimeout(() => {
                    const webUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                    window.open(webUrl, '_blank');
                }, 600);
                return;
            }
            const webUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            window.open(webUrl, '_blank');
        }
    </script>
</div>