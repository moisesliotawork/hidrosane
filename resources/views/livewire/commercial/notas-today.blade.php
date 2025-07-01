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
    <div class="overflow-x-auto">
        <div class="mobile-optimized">
            <div class="space-y-4">
                @forelse($this->notes as $note)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
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
                        <!-- Información del cliente -->
                        <h3 class="customer-name dark:text-white">{{ $note['customer'] }}</h3>
                        <p class="customer-address dark:text-white">{{ $note['primary_address'] }}</p>
                        <p class="customer-address dark:text-white">{{ $note['address_info'] }}</p>

                        @if($note['show_phone'])
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
                            <button class="action-button">Dentro</button>
                            <button class="action-button">Llévame</button>
                        </div>

                        <!-- Nuevo botón que ocupa todo el ancho -->
                        <div class="mt-1"> <!-- Margen superior pequeño para separar -->
                            <button class="action-button w-full"> <!-- w-full para que ocupe todo el ancho -->
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
    </script>

</div>