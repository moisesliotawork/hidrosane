<x-filament-panels::page>
    <style>
        @keyframes contratos-mes-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }

        .contratos-mes-var {
            font-weight: 800 !important;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
        }

        .contratos-mes-blink {
            animation: contratos-mes-blink 1s ease-in-out infinite;
        }

        .contratos-mes-var-down {
            color: #dc2626 !important;
        }

        .contratos-mes-var-up {
            color: #16a34a !important;
        }

        .contratos-mes-var-same {
            color: #2563eb !important;
        }

        .contratos-mes-total-cell .fi-ta-text-item-label,
        .contratos-mes-total-cell {
            font-size: 1.25rem !important;
            font-weight: 800 !important;
            text-align: center !important;
        }

        /* Misma altura que el resto de filas: TOTAL en una sola línea */
        .contratos-mes-section .fi-ta-summary-row .fi-ta-text-summary {
            display: flex !important;
            flex-direction: row !important;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding-top: 0.75rem !important;
            padding-bottom: 0.75rem !important;
            grid-template-rows: none !important;
        }

        .contratos-mes-section .fi-ta-summary-row .fi-ta-summary-row-heading {
            padding-top: 0.75rem !important;
            padding-bottom: 0.75rem !important;
        }

        .contratos-mes-section .fi-ta-summary-row .fi-ta-text-summary > span {
            font-size: 0.95rem !important;
            font-weight: 800 !important;
            color: #111827 !important;
            line-height: 1.25 !important;
        }

        html.dark .contratos-mes-section .fi-ta-summary-row .fi-ta-text-summary > span {
            color: #f3f4f6 !important;
        }

        .contratos-mes-section {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        html.dark .contratos-mes-section {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgb(17 24 39);
        }

        .contratos-mes-section__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.85rem 1rem;
            cursor: pointer;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
            background: #f9fafb;
            border: 0;
            text-align: left;
        }

        html.dark .contratos-mes-section__header {
            background: rgba(255, 255, 255, 0.04);
            color: #f3f4f6;
        }

        .contratos-mes-section__body {
            padding: 0.75rem 1rem 1rem;
            border-top: 1px solid #e5e7eb;
        }

        html.dark .contratos-mes-section__body {
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        .contratos-mes-detalle-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .contratos-mes-detalle-table th,
        .contratos-mes-detalle-table td {
            padding: 0.45rem 0.55rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        html.dark .contratos-mes-detalle-table th,
        html.dark .contratos-mes-detalle-table td {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .contratos-mes-detalle-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
        }

        .contratos-mes-estado {
            display: inline-flex;
            padding: 0.15rem 0.45rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.7rem;
        }

        .contratos-mes-estado--soft_delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .contratos-mes-estado--nuevo {
            background: #dcfce7;
            color: #166534;
        }

        .contratos-mes-estado--restaurado {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .contratos-mes-estado--borrado {
            background: #111827;
            color: #f9fafb;
        }

        .contratos-mes-subseccion {
            border: 1px solid #e5e7eb;
            border-radius: 0.55rem;
            margin-top: 0.75rem;
            overflow: hidden;
        }

        html.dark .contratos-mes-subseccion {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .contratos-mes-subseccion__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.65rem 0.85rem;
            cursor: pointer;
            font-weight: 800;
            font-size: 0.85rem;
            letter-spacing: 0.02em;
            border: 0;
            text-align: left;
        }

        .contratos-mes-subseccion__header--quitados {
            background: #fef2f2;
            color: #991b1b;
        }

        .contratos-mes-subseccion__header--agregados {
            background: #f0fdf4;
            color: #166534;
        }

        html.dark .contratos-mes-subseccion__header--quitados {
            background: #450a0a;
            color: #fecaca;
        }

        html.dark .contratos-mes-subseccion__header--agregados {
            background: #052e16;
            color: #bbf7d0;
        }

        .contratos-mes-subseccion__body {
            padding: 0.65rem 0.85rem 0.85rem;
            border-top: 1px solid #e5e7eb;
        }

        html.dark .contratos-mes-subseccion__body {
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        .contratos-mes-pdf-btn {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 0.4rem;
            background: #ea580c;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .contratos-mes-pdf-btn:hover {
            background: #c2410c;
            color: #fff;
        }

        .contratos-mes-numeros-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .contratos-mes-numeros-grupo {
            border: 1px solid #e5e7eb;
            border-radius: 0.55rem;
            padding: 0.7rem 0.85rem;
            background: #fafafa;
        }

        html.dark .contratos-mes-numeros-grupo {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
        }

        .contratos-mes-numeros-grupo__title {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin-bottom: 0.45rem;
        }

        .contratos-mes-numeros-grupo__count {
            font-weight: 800;
            font-size: 0.85rem;
            color: #374151;
        }

        html.dark .contratos-mes-numeros-grupo__count {
            color: #d1d5db;
        }

        .contratos-mes-numeros-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .contratos-mes-numeros-table th,
        .contratos-mes-numeros-table td {
            padding: 0.3rem 0.4rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            width: 12.5%;
            font-size: 0.78rem;
        }

        .contratos-mes-numeros-table th.sep,
        .contratos-mes-numeros-table td.sep {
            border-left: 2px solid #d1d5db;
        }

        html.dark .contratos-mes-numeros-table th.sep,
        html.dark .contratos-mes-numeros-table td.sep {
            border-left-color: rgba(255, 255, 255, 0.15);
        }

        html.dark .contratos-mes-numeros-table th,
        html.dark .contratos-mes-numeros-table td {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .contratos-mes-numeros-table th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
        }

        .contratos-mes-numeros-table td {
            font-weight: 700;
            color: #111827;
        }

        html.dark .contratos-mes-numeros-table td {
            color: #f3f4f6;
        }

        .contratos-mes-solo-nums-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.78rem;
        }

        .contratos-mes-solo-nums-table td {
            width: 10%;
            padding: 0.28rem 0.3rem;
            border: 1px solid #e5e7eb;
            text-align: center;
            font-weight: 700;
            color: #111827;
            word-break: break-word;
            transition: background-color 0.12s ease;
            user-select: none;
        }

        .contratos-mes-solo-nums-table td.is-clickable {
            cursor: pointer;
        }

        .contratos-mes-solo-nums-table td.is-clickable:hover {
            outline: 1px solid #f59e0b;
            outline-offset: -1px;
        }

        .contratos-mes-solo-nums-table td.mark-yellow {
            background: #fde047 !important;
            color: #713f12 !important;
        }

        .contratos-mes-solo-nums-table td.mark-red {
            background: #f87171 !important;
            color: #7f1d1d !important;
        }

        html.dark .contratos-mes-solo-nums-table td {
            border-color: rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
        }

        html.dark .contratos-mes-solo-nums-table td.mark-yellow {
            background: #ca8a04 !important;
            color: #fef9c3 !important;
        }

        html.dark .contratos-mes-solo-nums-table td.mark-red {
            background: #dc2626 !important;
            color: #fee2e2 !important;
        }

        .contratos-mes-recuperados-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.85rem;
        }

        .contratos-mes-recuperados-form input {
            flex: 1 1 12rem;
            min-width: 10rem;
            height: 2.1rem;
            padding: 0 0.65rem;
            border: 1px solid #9ca3af;
            border-radius: 0.4rem;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .contratos-mes-recuperados-form button {
            height: 2.1rem;
            padding: 0 0.85rem;
            border: 0;
            border-radius: 0.4rem;
            background: #16a34a;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .contratos-mes-recuperados-form button:hover {
            background: #15803d;
        }

        .list-contract-month-bar {
            -webkit-overflow-scrolling: touch;
        }
    </style>

    {{-- Variaciones de Contratos (collapsed by default) --}}
    <div
        class="contratos-mes-section"
        x-data="{ open: @entangle('variacionesOpen') }"
    >
        <button type="button" class="contratos-mes-section__header" @click="open = !open">
            <span>Variaciones de Contratos ({{ $this->variacionItems->count() }})</span>
            <span x-text="open ? '▾' : '▸'"></span>
        </button>

        <div class="contratos-mes-section__body" x-show="open" x-cloak>
            @include('filament.superAdmin.partials.month-year-badges', [
                'prefix' => 'var',
                'allLabel' => 'Todas las variaciones',
            ])

            @if ($this->variacionItems->isEmpty())
                <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
                    No hay detalles de variación para este filtro.
                </p>
            @else
                {{-- Contratos quitados (variación negativa) --}}
                <div
                    class="contratos-mes-subseccion"
                    x-data="{ openQuitados: {{ $this->contratosQuitados->isNotEmpty() ? 'true' : 'false' }} }"
                >
                    <button
                        type="button"
                        class="contratos-mes-subseccion__header contratos-mes-subseccion__header--quitados"
                        @click="openQuitados = !openQuitados"
                    >
                        <span>Contratos quitados ({{ $this->contratosQuitados->count() }})</span>
                        <span x-text="openQuitados ? '▾' : '▸'"></span>
                    </button>
                    <div class="contratos-mes-subseccion__body" x-show="openQuitados" x-cloak>
                        @include('filament.superAdmin.partials.variacion-items-table', [
                            'items' => $this->contratosQuitados,
                            'emptyMessage' => 'No hay contratos quitados para este filtro.',
                        ])
                    </div>
                </div>

                {{-- Contratos agregados (variación positiva) --}}
                <div
                    class="contratos-mes-subseccion"
                    x-data="{ openAgregados: {{ $this->contratosAgregados->isNotEmpty() ? 'true' : 'false' }} }"
                >
                    <button
                        type="button"
                        class="contratos-mes-subseccion__header contratos-mes-subseccion__header--agregados"
                        @click="openAgregados = !openAgregados"
                    >
                        <span>Contratos agregados ({{ $this->contratosAgregados->count() }})</span>
                        <span x-text="openAgregados ? '▾' : '▸'"></span>
                    </button>
                    <div class="contratos-mes-subseccion__body" x-show="openAgregados" x-cloak>
                        @include('filament.superAdmin.partials.variacion-items-table', [
                            'items' => $this->contratosAgregados,
                            'emptyMessage' => 'No hay contratos agregados para este filtro.',
                        ])
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Contratos recuperados --}}
    <div
        class="contratos-mes-section"
        x-data="{ open: @entangle('recuperadosOpen') }"
    >
        <button type="button" class="contratos-mes-section__header" @click="open = !open">
            <span>CONTRATOS RECUPERADOS ({{ count($this->contratosRecuperadosNumeros) }})</span>
            <span x-text="open ? '▾' : '▸'"></span>
        </button>

        <div class="contratos-mes-section__body" x-show="open" x-cloak>
            <form
                class="contratos-mes-recuperados-form"
                wire:submit.prevent="addContratoRecuperado"
            >
                <input
                    type="text"
                    wire:model="nuevoContratoRecuperado"
                    placeholder="Nº contrato admin recuperado"
                    autocomplete="off"
                >
                <button type="submit">Agregar</button>
            </form>

            @if (count($this->contratosRecuperadosNumeros) === 0)
                <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
                    Aún no hay contratos recuperados. Añade un nº de contrato admin arriba.
                </p>
            @else
                @php
                    $numsRec = collect($this->contratosRecuperadosNumeros)->values();
                    $colsRec = 10;
                    $porColRec = (int) ceil(max(1, $numsRec->count()) / $colsRec);
                    $columnasRec = [];
                    for ($c = 0; $c < $colsRec; $c++) {
                        $columnasRec[$c] = $numsRec->slice($c * $porColRec, $porColRec)->values();
                    }
                    $filasRec = max(1, ...array_map(fn ($col) => $col->count(), $columnasRec));
                @endphp
                <div style="overflow-x: auto;">
                    <table class="contratos-mes-solo-nums-table">
                        <tbody>
                            @for ($i = 0; $i < $filasRec; $i++)
                                <tr>
                                    @for ($c = 0; $c < $colsRec; $c++)
                                        @php $valor = $columnasRec[$c]->get($i); @endphp
                                        @if (filled($valor))
                                            <td
                                                class="is-clickable"
                                                x-data="{ mark: 0 }"
                                                @click="mark = (mark + 1) % 3"
                                                :class="{
                                                    'mark-yellow': mark === 1,
                                                    'mark-red': mark === 2
                                                }"
                                                title="Clic: amarillo → rojo → normal"
                                            >{{ $valor }}</td>
                                        @else
                                            <td></td>
                                        @endif
                                    @endfor
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Resumen mensual (colapsable) --}}
    <div
        class="contratos-mes-section"
        x-data="{ open: @entangle('resumenOpen') }"
    >
        <button type="button" class="contratos-mes-section__header" @click="open = !open">
            <span>Resumen por mes</span>
            <span x-text="open ? '▾' : '▸'"></span>
        </button>

        <div class="contratos-mes-section__body" x-show="open" x-cloak>
            @include('filament.superAdmin.partials.month-year-badges', [
                'prefix' => 'res',
                'allLabel' => 'Todos los meses',
            ])

            {{ $this->table }}
        </div>
    </div>

    {{-- Nº de contratos admin por mes --}}
    <div
        class="contratos-mes-section"
        x-data="{ open: @entangle('numerosOpen') }"
    >
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.85rem 1rem;background:#f9fafb;border-bottom:0;">
            <button type="button" class="contratos-mes-section__header" style="flex:1;padding:0;background:transparent;" @click="open = !open">
                <span>Nº contratos admin por mes ({{ $this->numerosAdminPorMes->sum('total') }})</span>
                <span x-text="open ? '▾' : '▸'"></span>
            </button>
            <a
                href="{{ $this->numerosPdfUrl() }}"
                target="_blank"
                rel="noopener"
                class="contratos-mes-pdf-btn"
                @click.stop
            >
                Previsualizar PDF
            </a>
        </div>

        <div class="contratos-mes-section__body" x-show="open" x-cloak>
            @include('filament.superAdmin.partials.month-year-badges', [
                'prefix' => 'num',
                'allLabel' => 'Todos los meses',
            ])

            @if ($this->numerosAdminPorMes->isEmpty())
                <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
                    No hay números de contrato admin para este filtro.
                </p>
            @else
                <div class="contratos-mes-numeros-list">
                    @foreach ($this->numerosAdminPorMes as $grupo)
                        @php
                            $mesNum = null;
                            try {
                                $mesNum = (int) \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $grupo->mes_key)->month;
                            } catch (\Throwable) {
                                $mesNum = null;
                            }
                            $mesBadge = $mesNum
                                ? (\App\Support\Filament\MonthYearBadgeFilter::monthBadges()[$mesNum] ?? null)
                                : null;
                        @endphp
                        <div class="contratos-mes-numeros-grupo">
                            <div class="contratos-mes-numeros-grupo__title">
                                @if ($mesBadge)
                                    <span
                                        style="display:inline-flex;align-items:center;height:1.55rem;padding:0 0.55rem;border-radius:999px;font-size:0.68rem;letter-spacing:0.01em;white-space:nowrap;font-weight:700;background:{{ $mesBadge['bg'] }};color:{{ $mesBadge['text'] }};border:1px solid {{ $mesBadge['border'] }};"
                                    >
                                        {{ $mesBadge['label'] }}
                                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $grupo->mes_key)->year }}
                                    </span>
                                @else
                                    <strong>{{ \App\Support\ContratosPorMesStats::labelForMonthKey((string) $grupo->mes_key) }}</strong>
                                @endif
                                <span class="contratos-mes-numeros-grupo__count">{{ $grupo->total }}</span>
                            </div>
                            @php
                                $pares = collect($grupo->contratos)->values();
                                $cols = 4;
                                $porCol = (int) ceil(max(1, $pares->count()) / $cols);
                                $columnas = [];
                                for ($c = 0; $c < $cols; $c++) {
                                    $columnas[$c] = $pares->slice($c * $porCol, $porCol)->values();
                                }
                                $filas = max(1, ...array_map(fn ($col) => $col->count(), $columnas));
                            @endphp
                            <div style="overflow-x: auto;">
                                <table class="contratos-mes-numeros-table">
                                    <thead>
                                        <tr>
                                            @for ($c = 0; $c < $cols; $c++)
                                                <th @class(['sep' => $c > 0])># Registro</th>
                                                <th># Contrato_admin</th>
                                            @endfor
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for ($i = 0; $i < $filas; $i++)
                                            <tr>
                                                @for ($c = 0; $c < $cols; $c++)
                                                    @php $item = $columnas[$c]->get($i); @endphp
                                                    <td @class(['sep' => $c > 0])>{{ $item->id ?? '' }}</td>
                                                    <td>{{ $item->nro_contr_adm ?? '' }}</td>
                                                @endfor
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Solo números de contratos (10 columnas) --}}
    <div
        class="contratos-mes-section"
        x-data="{ open: @entangle('soloNumerosOpen') }"
    >
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.85rem 1rem;background:#f9fafb;border-bottom:0;">
            <button type="button" class="contratos-mes-section__header" style="flex:1;padding:0;background:transparent;" @click="open = !open">
                <span>SOLO NUMEROS DE CONTRATOS ({{ count($this->soloNumerosContratos) }})</span>
                <span x-text="open ? '▾' : '▸'"></span>
            </button>
            <a
                href="{{ $this->soloNumerosPdfUrl() }}"
                target="_blank"
                rel="noopener"
                class="contratos-mes-pdf-btn"
                @click.stop
            >
                Previsualizar PDF
            </a>
        </div>

        <div class="contratos-mes-section__body" x-show="open" x-cloak>
            @include('filament.superAdmin.partials.month-year-badges', [
                'prefix' => 'solo',
                'allLabel' => 'Todos los meses',
            ])

            @if (count($this->soloNumerosContratos) === 0)
                <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
                    No hay números de contrato admin para este filtro.
                </p>
            @else
                @php
                    $nums = collect($this->soloNumerosContratos)->values();
                    $cols = 10;
                    $porCol = (int) ceil(max(1, $nums->count()) / $cols);
                    $columnas = [];
                    for ($c = 0; $c < $cols; $c++) {
                        $columnas[$c] = $nums->slice($c * $porCol, $porCol)->values();
                    }
                    $filas = max(1, ...array_map(fn ($col) => $col->count(), $columnas));
                @endphp
                <div style="overflow-x: auto;">
                    <table class="contratos-mes-solo-nums-table">
                        <tbody>
                            @for ($i = 0; $i < $filas; $i++)
                                <tr>
                                    @for ($c = 0; $c < $cols; $c++)
                                        @php $valor = $columnas[$c]->get($i); @endphp
                                        @if (filled($valor))
                                            <td
                                                class="is-clickable"
                                                x-data="{ mark: 0 }"
                                                @click="mark = (mark + 1) % 3"
                                                :class="{
                                                    'mark-yellow': mark === 1,
                                                    'mark-red': mark === 2
                                                }"
                                                title="Clic: amarillo → rojo → normal"
                                            >{{ $valor }}</td>
                                        @else
                                            <td></td>
                                        @endif
                                    @endfor
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Ver datos contrato (persistente vía sesión + barra global SuperAdmin) --}}
    <div class="contratos-mes-section">
        <div class="contratos-mes-section__header" style="cursor: default;">
            <span>VER DATOS CONTRATO</span>
        </div>

        <div class="contratos-mes-section__body">
            <p style="margin: 0 0 0.65rem; color: #6b7280; font-size: 0.8rem;">
                Busca por nº de contrato admin y abre el formulario del recurso Contratos.
                El valor se conserva al cambiar de recurso (también arriba del panel).
            </p>
            <form
                class="contratos-mes-recuperados-form"
                wire:submit.prevent="buscarDatosContrato"
            >
                <input
                    type="text"
                    wire:model.live.debounce.400ms="buscarNroContratoAdmin"
                    placeholder="Nº contrato admin"
                    autocomplete="off"
                >
                <button type="submit" style="background:#1d4ed8;">Buscar</button>
            </form>
        </div>
    </div>
</x-filament-panels::page>
