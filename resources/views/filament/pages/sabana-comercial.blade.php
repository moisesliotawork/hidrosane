<x-filament-panels::page>
    <div class="sabana-com">
        @if ($selectedUser)
            <div class="sabana-com__meta">
                <div class="sabana-com__meta-row">
                    <span class="sabana-com__chip sabana-com__chip--gold sabana-com__chip--user">{{ $userLabel }}</span>
                </div>

                @if ($periodoReparto)
                    <div class="sabana-com__meta-row">
                        <span class="sabana-com__label">Período de REPARTO:</span>
                        <span class="sabana-com__chip sabana-com__chip--gold">{{ $periodoReparto }}</span>
                    </div>
                @endif

                @if ($periodoVenta)
                    <div class="sabana-com__meta-row">
                        <span class="sabana-com__label">Período de VENTA:</span>
                        <span class="sabana-com__chip sabana-com__chip--gray">{{ $periodoVenta }}</span>
                    </div>
                @endif

                <p class="sabana-com__hint">(AMBAS FECHAS INCLUIDAS)</p>

                <div class="sabana-com__actions">
                    <button type="button" wire:click="backToList" class="sabana-com__btn sabana-com__btn--back">← Atrás</button>
                    <button
                        type="button"
                        wire:click="toggleDetalles"
                        class="sabana-com__btn sabana-com__btn--detalles"
                    >
                        @if ($this->hayAlgunaSeccionAbierta())
                            👁‍🗨 Ocultar detalles
                        @else
                            👁 Mostrar detalles
                        @endif
                    </button>
                    <div class="sabana-com__tabs">
                        <button
                            type="button"
                            wire:click="setPeriodoTab('anterior')"
                            class="sabana-com__tab {{ $this->periodoTab === 'anterior' ? 'is-active' : '' }}"
                        >
                            Mes Ant
                        </button>
                        <button
                            type="button"
                            wire:click="setPeriodoTab('este')"
                            class="sabana-com__tab {{ $this->periodoTab === 'este' ? 'is-active' : '' }}"
                        >
                            Este mes
                        </button>
                    </div>
                </div>
            </div>

            <button
                type="button"
                wire:click="toggleSeccion('entregadas')"
                class="sabana-com__section-banner {{ $this->seccionEntregadas ? 'is-open' : 'is-closed' }}"
            >
                <span>1. VENTAS ENTREGADAS COMISIONABLES (VENTAS CON ÉXITO SALVO ANULACIÓN FUTURA)</span>
                <span class="sabana-com__section-chevron">{{ $this->seccionEntregadas ? '▾' : '▸' }}</span>
            </button>
            @if ($this->seccionEntregadas)
                @include('filament.pages.partials.sabana-comercial-table', [
                    'filas' => $filasEntregadas,
                    'mostrarAnulacion' => false,
                ])
            @endif

            <button
                type="button"
                wire:click="toggleSeccion('neutras')"
                class="sabana-com__section-banner sabana-com__section-banner--neutras {{ $this->seccionNeutras ? 'is-open' : 'is-closed' }}"
            >
                <span>2. VENTAS NEUTRAS NO COMISIONABLES (ENTREGA Y ANULACIÓN EN ESTE MISMO PERIODO COMERCIAL)</span>
                <span class="sabana-com__section-chevron">{{ $this->seccionNeutras ? '▾' : '▸' }}</span>
            </button>
            @if ($this->seccionNeutras)
                @include('filament.pages.partials.sabana-comercial-table', [
                    'filas' => $filasNeutras,
                    'mostrarAnulacion' => true,
                ])
            @endif

            <button
                type="button"
                wire:click="toggleSeccion('descontar')"
                class="sabana-com__section-banner sabana-com__section-banner--descontar {{ $this->seccionDescontar ? 'is-open' : 'is-closed' }}"
            >
                <span>3. VENTAS A DESCONTAR COMISIÓN (VENTA EN PERIODO ANTERIOR Y ANULACIÓN EN ESTE PERIODO)</span>
                <span class="sabana-com__section-chevron">{{ $this->seccionDescontar ? '▾' : '▸' }}</span>
            </button>
            @if ($this->seccionDescontar)
                @include('filament.pages.partials.sabana-comercial-table', [
                    'filas' => $filasDescontar,
                    'mostrarAnulacion' => true,
                    'variante' => 'descontar',
                ])
            @endif

            @if ($resumen)
                <div class="sabana-com__resumen">
                    <div class="sabana-com__resumen-row">
                        Ventas comisionables: {{ $resumen['vip_ya_eran'] }} en VIPS que ya lo eran/
                        <span class="sabana-com__resumen-ok">
                            {{ $resumen['calle_nuevos'] }} en CALLE, NUEVOS CLIENTES GENERADOS
                        </span>
                    </div>
                    <div class="sabana-com__resumen-row">
                        Neutras:
                        <span class="sabana-com__resumen-bad">{{ $resumen['neutras_vip_perdidos'] }} VIPS PERDIDOS</span>
                        / {{ $resumen['neutras_calle'] }} en CALLE
                    </div>
                    <div class="sabana-com__resumen-row">
                        Recogidas anteriores:
                        <span class="sabana-com__resumen-bad">{{ $resumen['recogidas_vip_perdidos'] }} VIPS PERDIDOS</span>
                        / {{ $resumen['recogidas_calle'] }} en CALLE
                    </div>
                    <div class="sabana-com__resumen-row">
                        SALDO DE GENERACIÓN DE CLIENTES:
                        <span class="{{ $resumen['saldo'] >= 0 ? 'sabana-com__resumen-ok' : 'sabana-com__resumen-bad' }}">{{ $resumen['saldo'] }}</span>
                        ({{ $resumen['calle_nuevos'] }} - {{ $resumen['neutras_vip_perdidos'] }} - {{ $resumen['recogidas_vip_perdidos'] }})
                    </div>
                    <div class="sabana-com__kpi">
                        <div class="sabana-com__kpi-title">KPI ÉXITO COMERCIAL</div>
                        <div class="sabana-com__kpi-formula">Indicador: Ventas entregadas / brutas x 100</div>
                        <div class="sabana-com__kpi-value">
                            <span class="sabana-com__kpi-emoji">{{ $resumen['kpi_emoji'] }}</span>
                            <span>{{ $resumen['kpi'] }}%</span>
                        </div>
                    </div>

                    @php $dec = $resumen['declaraciones']; @endphp
                    <div class="sabana-com__decl">
                        <div class="sabana-com__decl-head">
                            <div class="sabana-com__decl-title">DECLARACIONES EN VISITAS</div>
                            <div class="sabana-com__decl-sub">Periodo de VENTA</div>
                            <div class="sabana-com__decl-hint">Este número representa las DECLARACIONES COMERCIALES TRAS VISITA</div>
                        </div>
                        <div class="sabana-com__decl-metrics">
                            <div class="sabana-com__decl-total">
                                <div class="sabana-com__decl-total-label">TOTAL CONF</div>
                                <div class="sabana-com__decl-total-value">{{ $dec['total_conf'] }}</div>
                                <div class="sabana-com__decl-total-pct">{{ $dec['total_conf_pct'] }}%</div>
                            </div>
                            <div class="sabana-com__decl-stat">
                                <div class="sabana-com__decl-stat-label">OFIS</div>
                                <div class="sabana-com__decl-stat-value">{{ $dec['ofis'] }}</div>
                            </div>
                            <div class="sabana-com__decl-stat">
                                <div class="sabana-com__decl-stat-label">NULOS</div>
                                <div class="sabana-com__decl-stat-value">{{ $dec['nulos'] }}</div>
                            </div>
                            <div class="sabana-com__decl-stat">
                                <div class="sabana-com__decl-stat-label">CONF</div>
                                <div class="sabana-com__decl-stat-value">{{ $dec['conf'] }}</div>
                            </div>
                            <div class="sabana-com__decl-stat">
                                <div class="sabana-com__decl-stat-label">VIS CON VENTA</div>
                                <div class="sabana-com__decl-stat-value">{{ $dec['vis_con_venta'] }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">VTAS. BRUTAS</div>
                        <div class="sabana-com__vta-card-desc">Ventas totales con entrega en el periodo comercial</div>
                        <div class="sabana-com__vta-card-value is-ok">
                            {{ $resumen['vtas_brutas'] }} <span>ventas</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">VTAS. NEUTRAS</div>
                        <div class="sabana-com__vta-card-desc">Anulaciones de ventas entregadas en este periodo</div>
                        <div class="sabana-com__vta-card-value is-bad">
                            {{ $resumen['vtas_neutras'] }} <span>ventas</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">VTAS. ENTREGADAS</div>
                        <div class="sabana-com__vta-card-desc">Ventas entregadas en el periodo</div>
                        <div class="sabana-com__vta-card-value is-ok">
                            {{ $resumen['vtas_entregadas'] }} <span>ventas</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">ANULACIONES ANTERIORES</div>
                        <div class="sabana-com__vta-card-desc">Ventas anteriores anuladas en este periodo</div>
                        <div class="sabana-com__vta-card-value is-bad">
                            {{ $resumen['anulaciones_anteriores'] }} <span>ventas</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card sabana-com__vta-card--netas">
                        <div class="sabana-com__vta-card-title">VTAS. NETAS</div>
                        <div class="sabana-com__vta-card-desc">Ventas entregadas menos anulaciones anteriores</div>
                        <div class="sabana-com__vta-card-value">
                            {{ $resumen['vtas_netas'] }} <span>ventas</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card sabana-com__vta-card--comis">
                        <div class="sabana-com__vta-card-title">VTAS. NETAS COMISIONABLES</div>
                        <div class="sabana-com__vta-card-desc">Ventas netas a partir de la 7ª venta del periodo</div>
                        <div class="sabana-com__vta-card-value">
                            {{ $resumen['vtas_netas_comisionables'] }} <span>ventas</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">RAPPEL</div>
                        <div class="sabana-com__vta-card-desc">
                            {{ $resumen['rappel_netas_label'] }} Vtas. netas que incrementan la Ref. de comisiones en:
                        </div>
                        <div class="sabana-com__vta-card-value is-ok">
                            {{ $resumen['rappel_ohanapoints'] }} <span>ohanapoints</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">
                            REFERENCIA COMISION (Base = {{ $resumen['ref_comision_base'] }} op)
                        </div>
                        <div class="sabana-com__vta-card-desc">Ref. Comision una vez incorporado el rappel</div>
                        <div class="sabana-com__vta-card-value is-ok">
                            {{ $resumen['ref_comision'] }}
                            <span>ohanapoints/Vta. neta comisionable</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">PENALIZACIÓN PUNTOS</div>
                        <div class="sabana-com__vta-card-desc">Importe penalizado por puntos pasados</div>
                        <div class="sabana-com__vta-card-value is-bad">
                            {{ $resumen['pen_puntos'] }} <span>ohanapoints</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">PENALIZACIÓN CARENCIA</div>
                        <div class="sabana-com__vta-card-desc">Importe penalizado por meses de carencia</div>
                        <div class="sabana-com__vta-card-value is-bad">
                            {{ $resumen['pen_carencia'] }} <span>ohanapoints</span>
                        </div>
                    </div>
                    <div class="sabana-com__vta-card">
                        <div class="sabana-com__vta-card-title">PENALIZACIÓN EDAD</div>
                        <div class="sabana-com__vta-card-desc">Importe penalizado por clientes mayores de 80 años</div>
                        <div class="sabana-com__vta-card-value is-bad">
                            {{ $resumen['pen_edad'] }} <span>ohanapoints</span>
                        </div>
                    </div>
                </div>
            @endif
        @else
            @foreach ([
                'jefes' => 'Jefes de equipo',
                'comerciales' => 'Comerciales',
                'repartidores' => 'Repartidores',
            ] as $key => $titulo)
                <section class="sabana-com__group">
                    <h3 class="sabana-com__group-title">{{ $titulo }}</h3>
                    <div class="sabana-com__people">
                        @forelse ($people[$key] as $persona)
                            <button
                                type="button"
                                wire:click="openUser({{ $persona->id }})"
                                class="sabana-com__person"
                            >
                                <span class="sabana-com__person-id">{{ $persona->empleado_id }}</span>
                                <span class="sabana-com__person-name">
                                    {{ mb_strtoupper(trim($persona->name.' '.($persona->last_name ?? ''))) }}
                                </span>
                            </button>
                        @empty
                            <p class="sabana-com__empty-inline">No hay {{ mb_strtolower($titulo) }} activos.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        @endif
    </div>

    <style>
        .fi-main:has(.sabana-com),
        .fi-page:has(.sabana-com) {
            background: #dff6e6;
        }
        html.dark .fi-main:has(.sabana-com),
        html.dark .fi-page:has(.sabana-com) {
            background: #143024;
        }
        .sabana-com { display:flex; flex-direction:column; gap:10px; }

        .sabana-com__group { margin-top:4px; }
        .sabana-com__group-title {
            margin:0 0 6px; font-size:13px; font-weight:800; color:#14532d;
            text-transform:uppercase; letter-spacing:.04em;
        }
        .sabana-com__people { display:flex; flex-direction:column; gap:4px; }
        .sabana-com__person {
            display:flex; align-items:center; gap:10px; width:100%;
            border:1px solid #d1d5db; border-radius:8px; padding:8px 12px;
            background:#fff; text-align:left; cursor:pointer;
        }
        .sabana-com__person:hover { background:#f3f4f6; }
        .sabana-com__person-id { font-size:16px; font-weight:800; color:#1e3a8a; min-width:3ch; }
        .sabana-com__person-name { font-size:14px; font-weight:700; color:#111827; }

        .sabana-com__meta { display:flex; flex-direction:column; gap:6px; }
        .sabana-com__meta-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .sabana-com__label { font-size:13px; font-weight:800; color:#111827; }
        .sabana-com__hint { margin:0; color:#9ca3af; font-size:11px; font-weight:600; }
        .sabana-com__actions {
            display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:4px;
        }
        .sabana-com__btn {
            border:0; border-radius:6px; padding:7px 12px;
            font-size:12px; font-weight:700; cursor:pointer;
        }
        .sabana-com__btn--back { background:#9ca3af; color:#fff; }
        .sabana-com__btn--detalles { background:#dc2626; color:#fff; }
        .sabana-com__tabs {
            display:inline-flex; gap:0; border:1px solid #9ca3af; border-radius:8px; overflow:hidden;
        }
        .sabana-com__tab {
            border:0; background:#e5e7eb; color:#4b5563;
            padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;
        }
        .sabana-com__tab + .sabana-com__tab { border-left:1px solid #9ca3af; }
        .sabana-com__tab.is-active { background:#16a34a; color:#fff; }
        .sabana-com__chip {
            border-radius:4px; padding:5px 12px; font-size:12px; font-weight:800;
        }
        .sabana-com__chip--gold { background:#a16207; color:#fff; }
        .sabana-com__chip--gray { background:#a8a29e; color:#fff; }
        .sabana-com__chip--user {
            font-size:24px;
            font-weight:900;
            padding:10px 22px;
            line-height:1.2;
            white-space:nowrap;
            display:inline-block;
            max-width:100%;
            overflow:hidden;
            text-overflow:ellipsis;
        }

        .sabana-com__section-banner {
            display:flex; align-items:center; justify-content:space-between; gap:10px;
            width:100%; border:0; text-align:left; cursor:pointer;
            background:#16a34a; color:#fff; font-size:11px; font-weight:800;
            letter-spacing:.03em; padding:8px 12px; border-radius:4px;
            text-transform:uppercase;
            margin-top:4px;
        }
        .sabana-com__section-banner:hover { filter:brightness(0.96); }
        .sabana-com__section-banner--neutras {
            background:#57534e;
        }
        .sabana-com__section-banner--descontar {
            background:#dc2626;
        }
        .sabana-com__section-chevron {
            flex:0 0 auto; font-size:14px; line-height:1;
        }

        .sabana-com__scroll {
            overflow-x:auto; border:1px solid #d1d5db; border-radius:6px; background:#fff;
        }
        .sabana-com__table {
            width:100%; min-width:1200px; border-collapse:collapse;
            font-size:11px; color:#111827;
        }
        .sabana-com__table th {
            background:#e5e7eb; color:#111827; font-size:13px; font-weight:900;
            letter-spacing:.03em; text-transform:uppercase; padding:8px 5px; white-space:nowrap;
            text-align:center; border-bottom:1px solid #d1d5db;
        }
        .sabana-com__table td {
            padding:7px 5px; border-top:1px solid #e5e7eb; text-align:center;
            vertical-align:middle; background:#fff;
        }
        .sabana-com__table td.is-left { text-align:left; }
        .sabana-com__table tbody tr:hover td { background:#f3f4f6; }
        .sabana-com__table .col-num { width:28px; }
        .sabana-com__table .col-cliente { min-width:160px; }
        .sabana-com__table td.is-ids {
            font-size:16px;
            font-weight:700;
        }
        .sabana-com__table td.is-hl {
            background:#ffff00 !important;
            font-weight:800;
            box-shadow: inset 0 0 0 1px #facc15;
        }
        .sabana-com__table tbody tr:hover td.is-hl {
            background:#f7ff00 !important;
        }
        .sabana-com__contrato {
            display:inline-block; background:#a16207; color:#fff; border-radius:4px;
            padding:2px 8px; font-weight:700; font-size:11px;
        }
        .sabana-com__table .is-cliente { font-weight:800; font-size:13px; }
        .sabana-com__cliente-badges {
            display:flex; flex-direction:column; align-items:flex-start; gap:3px; margin-top:3px;
        }
        .sabana-com__fuente {
            display:inline-block; border-radius:4px; padding:2px 8px;
            font-size:10px; font-weight:800; line-height:1.2; color:#fff; white-space:nowrap;
        }
        .sabana-com__fuente.is-vip { background:#a16207; }
        .sabana-com__fuente.is-calle { background:#44403c; }
        .sabana-com__fuente.is-ptafria { background:#b91c1c; }
        .sabana-com__fuente.is-nuevo { background:#15803d; }
        .sabana-com__fuente.is-perdido { background:#dc2626; font-style:italic; }
        .sabana-com__fecha-entr {
            display:inline-block; background:#ea580c; color:#fff; border-radius:4px;
            padding:2px 8px; font-weight:700; font-size:11px; white-space:nowrap;
        }
        .sabana-com__fecha-anul {
            display:inline-block; background:#78716c; color:#fff; border-radius:4px;
            padding:2px 8px; font-weight:700; font-size:11px; white-space:nowrap;
        }
        .sabana-com__table .is-estado { color:#15803d; font-weight:800; font-size:13px; }
        .sabana-com__table .is-estado.is-neutra { color:#c2410c; }
        .sabana-com__table .is-money { color:#15803d; font-weight:800; font-size:14px; white-space:nowrap; }
        .sabana-com__table .is-money-muted { color:#9ca3af; font-weight:800; font-size:14px; white-space:nowrap; }
        .sabana-com__table .is-vta { color:#15803d; font-weight:900; font-size:15px; }
        .sabana-com__pct {
            display:block; color:#ea580c; font-size:11px; font-weight:800; line-height:1.1;
        }
        .sabana-com__edad-flag { font-weight:800; font-size:13px; }
        .sabana-com__edad-detail { font-size:11px; color:#4b5563; line-height:1.2; }
        .sabana-com__table .is-pagando { font-weight:800; font-size:15px; }
        .sabana-com__table.is-descontar .is-estado,
        .sabana-com__table.is-descontar .is-money,
        .sabana-com__table.is-descontar .is-vta {
            color:#dc2626 !important;
        }
        .sabana-com__empty,
        .sabana-com__empty-inline {
            padding:16px; text-align:center; color:#6b7280; font-size:13px;
        }

        .sabana-com__resumen {
            display:flex; flex-direction:column; gap:8px; margin-top:10px;
        }
        .sabana-com__resumen-row {
            background:#fff; border:1px solid #d1d5db; border-radius:6px;
            padding:10px 14px; font-size:13px; font-weight:600; color:#111827;
            line-height:1.4;
        }
        .sabana-com__resumen-ok {
            color:#16a34a; font-weight:800;
        }
        .sabana-com__resumen-bad {
            color:#dc2626; font-weight:800;
        }
        .sabana-com__kpi {
            background:#16a34a; border-radius:8px; padding:14px 16px; color:#fff;
        }
        .sabana-com__kpi-title {
            font-size:15px; font-weight:900; letter-spacing:.04em; text-transform:uppercase;
        }
        .sabana-com__kpi-formula {
            margin-top:2px; font-size:12px; font-weight:600; opacity:.95;
        }
        .sabana-com__kpi-value {
            margin-top:8px; display:flex; align-items:center; gap:10px;
            font-size:36px; font-weight:900; line-height:1;
        }
        .sabana-com__kpi-emoji { font-size:34px; line-height:1; }

        .sabana-com__decl {
            background:#dc2626; border-radius:8px; padding:14px 16px; color:#fff;
        }
        .sabana-com__decl-title {
            font-size:15px; font-weight:900; letter-spacing:.04em; text-transform:uppercase;
        }
        .sabana-com__decl-sub { margin-top:2px; font-size:13px; font-weight:700; }
        .sabana-com__decl-hint { margin-top:2px; font-size:11px; font-weight:600; opacity:.95; }
        .sabana-com__decl-metrics {
            margin-top:12px; display:flex; flex-wrap:wrap; align-items:stretch; gap:14px 18px;
        }
        .sabana-com__decl-total {
            background:#fff; color:#111827; border-radius:6px; padding:10px 14px;
            min-width:88px; text-align:center;
            display:flex; flex-direction:column; justify-content:center; gap:2px;
        }
        .sabana-com__decl-total-label { font-size:10px; font-weight:800; letter-spacing:.03em; }
        .sabana-com__decl-total-value { font-size:28px; font-weight:900; line-height:1.1; }
        .sabana-com__decl-total-pct { font-size:16px; font-weight:800; }
        .sabana-com__decl-stat {
            display:flex; flex-direction:column; justify-content:center; gap:4px; min-width:70px;
        }
        .sabana-com__decl-stat-label {
            font-size:11px; font-weight:800; letter-spacing:.03em; text-transform:uppercase; opacity:.95;
        }
        .sabana-com__decl-stat-value { font-size:22px; font-weight:900; line-height:1; }

        .sabana-com__vta-card {
            background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px;
        }
        .sabana-com__vta-card-title {
            font-size:13px; font-weight:900; letter-spacing:.03em; text-transform:uppercase; color:#111827;
        }
        .sabana-com__vta-card-desc {
            margin-top:2px; font-size:12px; color:#6b7280; font-weight:600;
        }
        .sabana-com__vta-card-value {
            margin-top:8px; font-size:28px; font-weight:900; line-height:1.1;
        }
        .sabana-com__vta-card-value span {
            font-size:13px; font-weight:700;
        }
        .sabana-com__vta-card-value.is-ok { color:#16a34a; }
        .sabana-com__vta-card-value.is-bad { color:#ea580c; }
        .sabana-com__vta-card--netas {
            background:#16a34a; border-color:#16a34a; color:#fff;
        }
        .sabana-com__vta-card--netas .sabana-com__vta-card-title,
        .sabana-com__vta-card--netas .sabana-com__vta-card-value,
        .sabana-com__vta-card--netas .sabana-com__vta-card-value span { color:#fff; }
        .sabana-com__vta-card--netas .sabana-com__vta-card-desc { color:rgba(255,255,255,.9); }
        .sabana-com__vta-card--comis {
            background:#14532d; border-color:#14532d; color:#fff;
        }
        .sabana-com__vta-card--comis .sabana-com__vta-card-title,
        .sabana-com__vta-card--comis .sabana-com__vta-card-value,
        .sabana-com__vta-card--comis .sabana-com__vta-card-value span { color:#fff; }
        .sabana-com__vta-card--comis .sabana-com__vta-card-desc { color:rgba(255,255,255,.9); }
    </style>
</x-filament-panels::page>
