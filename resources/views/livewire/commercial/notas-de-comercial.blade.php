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

        .phone-buttons-container {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.4rem;
        }

        .phone-button {
            font-size: 0.7rem;
            padding: 0.3rem 0.5rem;
            border-radius: 0.4rem;
            background-color: #bfdbfe;
            color: #1e3a5f;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .phone-button:hover {
            background-color: #93c5fd;
        }

        .dark .phone-button {
            color: #0b1120;
        }

        /* Botón verde del mismo estilo */
        .action-button.green {
            background-color: #16a34a;
        }

        /* green-600 */
        .action-button.green:hover {
            background-color: #15803d;
        }

        /* Botón rosado pequeño (header) */
        .action-button.pink {
            background-color: #ec4899;
            /* pink-500 */
        }

        .action-button.pink:hover {
            background-color: #db2777;
            /* pink-600 */
        }

        .action-button.small {
            padding: 0.28rem 0.45rem;
            font-size: 0.65rem;
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

        @keyframes parpadeo-com {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0; }
        }

        .notas-counter-com {
            font-weight: 700;
            font-size: .85rem;
            margin: .25rem 0 .75rem 0;
            color: #166534;
        }

        .dark .notas-counter-com {
            color: #ffffff;
        }

        .notas-counter-com .num {
            animation: parpadeo-com 1s step-start infinite;
            color: #dc2626;
            font-size: 1.25rem;
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
        @php
            $canSendToReten = in_array(auth()->id(), [17, 18, 57], true);
        @endphp

        <div class="flex flex-wrap items-center gap-2">
            <div class="text-xs text-green-800 dark:text-gray-400 mr-2">
                Seleccionadas: <span class="font-semibold">{{ count($selectedNotes) }}</span>
            </div>

            @php
                $haySeleccion   = count($selectedNotes) > 0;
                $reasignarAct   = $haySeleccion;
                $oficinaAct     = $haySeleccion && $esReten;
                $retenAct       = $haySeleccion && $canSendToReten && !$esReten;
            @endphp

            {{-- Fila 1: Seleccionar / Deseleccionar --}}
            <div class="flex gap-2 w-full">
                <button class="action-button small"
                    style="flex:1;background-color:#166534;"
                    wire:click="selectAll">
                    SELECCIONAR TODOS
                </button>
                <button class="action-button small"
                    style="flex:1;background-color:#166534;"
                    wire:click="deselectAll">
                    QUITAR SELECCION
                </button>
            </div>

            {{-- Fila 2: REASIGNAR / Enviar a Oficina / Enviar a Retén --}}
            <div class="flex gap-2 w-full">
                <button class="action-button small"
                    style="flex:1;{{ $reasignarAct ? 'background-color:#2563eb;' : 'opacity:.35;cursor:not-allowed;' }}"
                    wire:click="openBulkReassignModal" @disabled(!$reasignarAct)>
                    REASIGNAR
                </button>
                <button class="action-button pink small"
                    style="flex:1;{{ $oficinaAct ? '' : 'opacity:.35;cursor:not-allowed;' }}"
                    type="button"
                    onclick="enviarAOficinaConGps('sendSelectedToOfficeFromReten')"
                    @disabled(!$oficinaAct)>
                    Enviar a Oficina
                </button>
                <button class="action-button green small"
                    style="flex:1;{{ $retenAct ? '' : 'opacity:.35;cursor:not-allowed;' }}"
                    wire:click="sendSelectedToReten" @disabled(!$retenAct)>
                    Enviar a Retén
                </button>
            </div>
        </div>

        <p class="notas-counter-com">
            Este comercial tiene &nbsp;&nbsp;<span class="num">{{ count($this->notesToday) }}</span>&nbsp;&nbsp;&nbsp; para hoy.
            Notas anteriores &nbsp;&nbsp;<span class="num">{{ count($this->notesAll) }}</span>
        </p>

        {{-- ======= Sección: Notas de HOY ======= --}}
        <x-filament::section heading="Notas de hoy">
            <div class="space-y-4">
                @forelse($this->notesToday as $note)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div
                            class="flex items-center gap-2 ml-3 w-full justify-end sm:w-auto sm:justify-start sm:basis-auto basis-full">
                            <input type="checkbox" class="note-checkbox" wire:model.live="selectedNotes"
                                value="{{ $note['id'] }}" />
                            <span class="text-xs text-gray-500 dark:text-gray-400"></span>
                        </div>

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
                        <p class="customer-address dark:text-white">
                            {{ $note['full_address'] }}
                        </p>



                        @php
                            $phone1Raw = $note['phone'] ?? null;
                            $phone2Raw = $note['secondary_phone'] ?? null;
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
                            @if($note['de_camino'] ?? false)
                                <button class="action-button" wire:click="toggleDeCamino({{ $note['id'] }})">De Camino</button>
                            @else
                                <button class="action-button" onclick="toggleDeCaminoConGps({{ $note['id'] }})">De Camino</button>
                            @endif
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

                        <div
                            class="flex items-center gap-2 ml-3 w-full justify-end sm:w-auto sm:justify-start sm:basis-auto basis-full">
                            <input type="checkbox" class="note-checkbox" wire:model.live="selectedNotes"
                                value="{{ $note['id'] }}" />
                            <span class="text-xs text-gray-500 dark:text-gray-400"></span>
                        </div>

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
                        <p class="customer-address dark:text-white">
                            {{ $note['full_address'] }}
                        </p>


                        @php
                            $phone1Raw = $note['phone'] ?? null;
                            $phone2Raw = $note['secondary_phone'] ?? null;
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
                            @if($note['de_camino'] ?? false)
                                <button class="action-button" wire:click="toggleDeCamino({{ $note['id'] }})">De Camino</button>
                            @else
                                <button class="action-button" onclick="toggleDeCaminoConGps({{ $note['id'] }})">De Camino</button>
                            @endif
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
                <h3 class="text-lg font-semibold mb-4">
                    {{ $esReten ? 'Reasignar (sale de Retén)' : 'Reasignar' }}
                </h3>

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

    {{-- ===== Modal Reasignación Masiva ===== --}}
    @if($showBulkReassignModal)
        <div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50"
            wire:keydown.escape="$set('showBulkReassignModal', false)">
            <div class="bg-white dark:bg-gray-900 dark:text-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold mb-1">Reasignar Selección</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    {{ count($selectedNotes) }} nota(s) seleccionada(s)
                </p>

                <label class="block text-sm mb-2">Destino</label>
                <select wire:model="bulkNewComercialId"
                    class="w-full border rounded p-2 bg-white dark:bg-gray-900 dark:text-white">
                    <option value="">-- Elegir destino --</option>
                    <option value="reten">⬛ RETÉN</option>
                    @foreach($this->comerciales as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="mt-6 flex gap-2">
                    <button wire:click="$set('showBulkReassignModal', false)"
                        class="flex-1 px-3 py-2 rounded border border-gray-300 dark:border-gray-700">
                        Cancelar
                    </button>
                    <button wire:click="reassignBulkVisit" class="flex-1 px-3 py-2 rounded text-white"
                        style="background-color:#2563eb">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
@include('components.gps-livewire-nota-scripts')
@include('filament.commercial.components.bulk-oficina-gps-script')
</div>