@php
    use Carbon\Carbon;

    $yRep = 25;   // misma línea que "Com."
    $xRep = 159.3;  // columna derecha (donde dice Rep.: en el fondo)

    // Empleado del repartidor asociado a esta venta
    $repEmpleado = $venta->repartidor?->empleado_id;

    $debug = request()->boolean('debug');

    // ====== controles de calibración ======
    $dx = (float) request('dx', 0);   // desplazamiento global X (mm)
    $dy = (float) request('dy', 0);   // desplazamiento global Y (mm)
    $sx = (float) request('sx', 1);   // escala global X
    $sy = (float) request('sy', 1);   // escala global Y
    $row = (float) request('row', 7.2); // alto/step de fila

    // ===== Helpers =====
    $fecPromo = optional(Carbon::parse($venta->created_at))->format('d-m-Y');
    $fecEntr = $venta->fecha_entrega ? Carbon::parse($venta->fecha_entrega)->format('d-m-Y') : '';

    // Items: EXPLOTAR por cantidad y tomar 10
    $rawLines = $venta->ventaOfertas->flatMap(fn($o) => $o->productos);
    $items = $rawLines->flatMap(function ($line) {
        $qty = max((int) ($line->cantidad ?? 1), 1);
        return collect(array_fill(0, $qty, $line));
    })->values()->take(10);

    $columns = $items->chunk(5)->map->values();
    $colA = $columns->get(0, collect());
    $colB = $columns->get(1, collect());

    // Tamaño de fuente para la descripción según longitud (11 → 8 pt)
    $descFont = function (string $text): float {
        $len = mb_strlen(trim($text), 'UTF-8');
        if ($len <= 30)
            return 11;
        if ($len <= 36)
            return 10;
        if ($len <= 42)
            return 9;
        if ($len <= 50)
            return 8.5;
        return 8; // muy largo
    };

    // ===== Coordenadas fijas en mm (las existentes NO se tocan) =====
    $yCodContrato = 12.5;
    $xCodContrato = 134.5;
    $yFecPromo = 12.3;
    $xFecPromo = 171.4;
    $yFecEntr = 16.5;
    $xFecEntr = 166.5;
    $yHoraEntr = 20.8;
    $xHoraEntr = 168.3;
    $yCodCliente = 16.5;
    $xCodCliente = 131.5;
    $yComercial = 25;
    $xComercial = 121.0;

    $yA_Nombre = 47.7;
    $xA_Nombre = 45;
    $yA_Dni = 51.7;
    $xA_Dni = 25.5;
    $yA_Nac = 56.3;
    $xA_Nac = 42.5;

    $yA_Dir = 47.7;
    $xA_Dir = 129.5;

    // Direccion en 2 líneas (cada una con su propia posición)
    $yA_DirL1 = $yA_Dir;
    $xA_DirL1 = 129.5;
    $yA_DirL2 = $yA_Dir + 4.2;
    $xA_DirL2 = 111.5;

    // Anchos independientes
    $wDirL1 = 75.0;
    $wDirL2 = 90.0;

    // ===== NUEVOS CAMPOS (coordenadas) =====
    // Izquierda
    $yA_EstadoCivil = 60.3;
    $xA_EstadoCivil = 31.5;   // “Estado civil:”
    $yA_SitLab = 64.7;
    $xA_SitLab = 40.1;        // “Situación laboral:”
    // Derecha
    $yA_Telefonos = 56.2;
    $xA_Telefonos = 129.5;    // “Teléfonos:”
    $yA_Vivienda = 60.3;
    $xA_Vivienda = 128.1;     // “Vivienda:”
    $yA_Ingresos = 64.6;
    $xA_Ingresos = 128.5;     // “Ingresos:”

    // Tabla productos P1
    $yBase = 94.1;
    $xPosA = 15.0;
    $xDesA = 32.0;
    $xPosB = 111.0;
    $xDesB = 130.0;

    $wDesA = 72.0;
    $wDesB = 72.0;

    // Pagos P1
    $yPagoFila = 151.5;
    $xEntrada = 16.8;
    $wEntrada = 30.0;
    $xNumCuotas = 53.4;
    $wNumCuotas = 30;
    $xCuota = 91.8;
    $wCuota = 28.0;
    $xMes1 = 130.2;
    $wMes1 = 28.0;
    $xImporte = 168.6;
    $wImporte = 25.0;

    // IBAN y firmas
    $yIban = 159.4;
    $xIban = 88.8;
    $wIban = 110.0;

    $yFirmas = 267;
    $xFirmaCli = 11.0;
    $xFirmaEmp = 131.0;
    $wFirma = 70.0;

    // Página 2 (DNI)
    $yP2_Dni = 245;
    $xP2_Dni = 45;

    // ===== Valores formateados (labels) =====
    $estadoCivil = (function ($v) {
        $e = \App\Enums\EstadoCivil::tryFrom($v ?? '');
        return $e ? $e->label() : '';
    })($venta->customer->estado_civil ?? null);

    $mostrarSitLab = (bool) ($venta->mostrar_situacion_lab ?? true);
    $sitLab = $mostrarSitLab
        ? (function ($v) {
            $e = \App\Enums\SituacionLaboral::tryFrom($v ?? '');
            return $e ? $e->label() : '';
        })($venta->customer->situacion_laboral ?? null)
        : '';

    $mostrarVivienda = (bool) ($venta->mostrar_tipo_vivienda ?? true);
    $vivienda = $mostrarVivienda
        ? (function ($v) {
            $e = \App\Enums\TipoVivienda::tryFrom($v ?? '');
            return $e ? $e->label() : '';
        })($venta->customer->tipo_vivienda ?? null)
        : '';

    $telefonos = collect([$venta->customer->phone ?? null, $venta->customer->secondary_phone ?? null])
        ->filter()->implode(' / ');

    $mostrarIngresos = (bool) ($venta->mostrar_ingresos ?? true);
    $ingresos = $mostrarIngresos ? mb_strtoupper($venta->customer->ingresos_rango ?? '', 'UTF-8') : '';

    // Dirección 2 líneas
    $primary = trim((string) ($venta->customer->primary_address ?? ''));
    $nroPiso = trim((string) ($venta->customer->nro_piso ?? ''));   // ← piso
    $postalCode = trim((string) ($venta->customer->postal_code ?? ''));
    $city = trim((string) ($venta->customer->ciudad ?? ''));
    $province = trim((string) ($venta->customer->provincia ?? ''));
    $ayto = trim((string) ($venta->customer->ayuntamiento ?? ''));
    $cpCity = trim(implode(' ', array_filter([$postalCode, $city])));

    // FIX letra huérfana tras CP
    $cpCity = preg_replace('/^(\d{4,5})\s+[A-ZÁÉÍÓÚÑ]\b\s+/u', '$1 ', $cpCity);
    $provinceFormatted = $province ? "($province)" : null;

    // Línea 1: dirección + piso SIEMPRE que exista
    $dirL1Parts = [];
    if ($primary !== '') {
        $dirL1Parts[] = $primary;
    }
    if ($nroPiso !== '') {
        $dirL1Parts[] = $nroPiso;   // ← piso pasa a la primera línea
    }
    $dirL1 = implode(' ', $dirL1Parts);

    // Línea 2: CP+Ciudad → ayto (ya SIN piso)
    $dirL2Parts = [];
    if ($cpCity !== '') {
        $dirL2Parts[] = $cpCity;
    }
    if ($ayto !== '') {
        $dirL2Parts[] = $ayto;
    }

    $dirL2 = implode(' - ', $dirL2Parts);


    if ($provinceFormatted) {
        $dirL2 = trim($dirL2 . ' ' . $provinceFormatted);
    }

    $toTitleCase = function (?string $text): string {
        $t = trim((string) $text);
        if ($t === '')
            return '';
        $t = mb_strtolower($t, 'UTF-8');
        return mb_convert_case($t, MB_CASE_TITLE, "UTF-8");
    };
    $dirL1 = $toTitleCase($dirL1);
    $dirL2 = $toTitleCase($dirL2);

    // Delegación
    $yDelegacion = 20.9;
    $xDelegacion = 121.8;
    $delegacionNombre = 'VIGO';

    /* ====== P3 ALBARÁN (PAG-1) ====== */
    $dirOneLine = trim(preg_replace('/\s+/', ' ', trim($dirL1 . ($dirL2 ? ' - ' . $dirL2 : ''))), ' -');
    $yAlbContrato = 117.0;
    $xAlbContrato = 156.3;
    $yAlbNombre = 78;
    $xAlbNombre = 53.0;
    $yAlbDni = 82.2;
    $xAlbDni = 36.0;
    $yAlbTelf = 103.4;
    $xAlbTelf = 38.0;
    $yAlbDir = 86.4;
    $xAlbDir = 38.0;
    $wAlbDir = 165.0;

    $yAlbBase = 124.0;
    $xAlbPos = 23.0;
    $xAlbDesc = 40.0;
    $wAlbDesc = 150.0;
    $rowAlb = 8;
    $maxAlbRows = 14;
    $itemsAlb = $rawLines->flatMap(function ($line) {
        $qty = max((int) ($line->cantidad ?? 1), 1);
        return collect(array_fill(0, $qty, $line));
    })->values()->take($maxAlbRows);

    /* ====== P4 ALBARÁN (PAG-2) ====== */
    if (!isset($dirOneLine)) {
        $dirOneLine = trim(preg_replace('/\s+/', ' ', trim(($dirL1 ?? '') . (($dirL2 ?? '') ? ' - ' . $dirL2 : ''))), ' -');
    }
    $yB2_Nombre = 113.3;
    $xB2_Nombre = 53.0;
    $yB2_DNI_1 = 117.5;
    $xB2_DNI_1 = 36.0;
    $yB2_Telf = 143;
    $xB2_Telf = 38.0;
    $yB2_Dir = 121.7;
    $xB2_Dir = 38.0;
    $wB2_Dir = 165.0;
    $yB2_DNI_2 = 210.3;
    $xB2_DNI_2 = 44.8;
    $yB2_Contrato = (float) request('yb2_contrato', 84.5);
    $xB2_Contrato = (float) request('xb2_contrato', 150.3);

    /* ====== P5 APERTURA / DESEMBALAJE ====== */
    if (!isset($dirOneLine)) {
        $dirOneLine = trim(preg_replace('/\s+/', ' ', trim(($dirL1 ?? '') . (($dirL2 ?? '') ? ' - ' . $dirL2 : ''))), ' -');
    }
    $yAp_Contrato = 59.9;
    $yAp_Nombre = 64.1;
    $yAp_Dni1 = 68.3;
    $yAp_Dir = 72.5;
    $yAp_Tel = 80.9;
    $yAp_Dni2 = 227.1;
    $xApContrato = (float) request('xap_contrato', 47.0);
    $xApNombre = (float) request('xap_nombre', 43.0);
    $xApDni1 = (float) request('xap_dni1', 25.4);
    $xApDir = (float) request('xap_dir', 28.7);
    $xApTel = (float) request('xap_tel', 27.0);
    $xApDni2 = (float) request('xap_dni2', 120.0);
    $wApDir = (float) request('wap_dir', 160.0);

    // ===== Pasadas: 0 = Original (con anexos), 1 = Copia (con anexos, sin productos ni importes), 2 = Copia-B (igual que copia, pero nro_contr_adm -B) =====
    $__passes = [0, 1, 2];

    // ===== Año del contrato (año de la fecha de venta) =====
    // Ajusta el campo de fecha según tu modelo: fecha_venta / sale_date / created_at, etc.
    $fechaVenta = $venta->fecha_venta
        ? Carbon::parse($venta->fecha_venta)
        : Carbon::parse($venta->created_at);

    $anioContrato = $fechaVenta->format('Y');

    // ===== Footer (abajo) =====
    $yYear = (float) request('yyear', 283.0); // mm (casi al final de la hoja A4)
    $xYear = (float) request('xyear', 122.0); // centro horizontal (105mm en A4)
    $wYear = (float) request('wyear', 60.0);  // ancho del bloque para centrar texto

    $anioContrato2 = $fechaVenta->format('Y');

    // ===== Footer (abajo) =====
    $yYear2 = (float) request('yyear', 283.0); // mm (casi al final de la hoja A4)
    $xYear2 = (float) request('xyear', 99.0); // centro horizontal (105mm en A4)
    $wYear2 = (float) request('wyear', 60.0);  // ancho del bloque para centrar texto

    $anioContrato3 = $fechaVenta->format('Y');

    // ===== Footer (abajo) =====
    $yYear3 = (float) request('yyear', 265.0); // mm (casi al final de la hoja A4)
    $xYear3 = (float) request('xyear', 143.0); // centro horizontal (105mm en A4)
    $wYear3 = (float) request('wyear', 60.0);  // ancho del bloque para centrar texto

@endphp

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <title>Contrato OHANA</title>
    <style>
        @page {
            size: 210mm 297mm;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        .page {
            position: relative;
            width: 210mm;
            height: 297mm;
            overflow: hidden;
        }

        .bg {
            position: absolute;
            inset: 0;
            width: 210mm;
            height: 297mm;
            z-index: 0;
        }

        .surface {
            position: absolute;
            inset: 0;
            transform-origin: 0 0;
        }

        .field {
            position: absolute;
            z-index: 1;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1;
            white-space: nowrap;
        }

        .field--sm {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8pt;
        }

        .sig {
            position: absolute;
            z-index: 1;
        }

        @if($debug)
            .field {
                outline: .25mm dashed red;
                background: rgba(255, 0, 0, .06);
            }

            .grid .h {
                position: absolute;
                left: 0;
                right: 0;
                height: 0;
                border-top: .1mm dotted #ccc;
            }

            .grid .v {
                position: absolute;
                top: 0;
                bottom: 0;
                width: 0;
                border-left: .1mm dotted #ccc;
            }

        @endif
    </style>
</head>

<body>

    @foreach($__passes as $__pass)
        @php
            // En 0 = original; 1 y 2 son copias (ocultan artículos e importes)
            $isCopia = ($__pass !== 0);
            // Solo en la tercera pasada (2) agregamos el sufijo -B al nro de contrato
            $contratoFmt = $venta->nro_contr_adm . ($__pass === 2 ? '-B' : '');
        @endphp

        {{-- ================= PÁGINA 1 – CONTRATO ================= --}}
        <div class="page">
            <img class="bg" src="{{ str_replace('\\', '/', public_path('templates/contrato-ohana-vacio-1.png')) }}"
                alt="Fondo P1">
            <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">

                {{-- Encabezado --}}
                <div class="field" style="top:{{ $yCodContrato }}mm; left:{{ $xCodContrato }}mm;">
                    {{ $contratoFmt }}</div>
                <div class="field" style="top:{{ $yFecPromo }}mm; left:{{ $xFecPromo }}mm;">{{ $fecPromo }}</div>
                <div class="field" style="top:{{ $yFecEntr }}mm; left:{{ $xFecEntr }}mm;">{{ $fecEntr }}</div>
                <div class="field" style="top:{{ $yHoraEntr }}mm; left:{{ $xHoraEntr }}mm;">
                    {{ strtoupper($venta->horario_entrega ?? '') }}</div>
                <div class="field" style="top:{{ $yDelegacion }}mm; left:{{ $xDelegacion }}mm;">{{ $delegacionNombre }}
                </div>
                <div class="field" style="top:{{ $yCodCliente }}mm; left:{{ $xCodCliente }}mm;">
                    {{ $venta->nro_cliente_adm }}</div>
                <div class="field" style="top:{{ $yComercial }}mm; left:{{ $xComercial }}mm;">
                    @php
                        $codCom = $venta->comercial->empleado_id ?? '';
                        $codComp = $venta->companion_id ? (optional($venta->companion)->empleado_id ?? $venta->companion_id) : null;
                    @endphp
                    {{ $codComp ? ($codCom . ' - ' . $codComp) : $codCom }}
                </div>

                {{-- Datos personales --}}
                <div class="field" style="top:{{ $yA_Nombre }}mm; left:{{ $xA_Nombre }}mm;">
                    {{ ucwords(trim(($venta->customer->first_names ?? '') . ' ' . ($venta->customer->last_names ?? ''))) }}
                </div>
                <div class="field" style="top:{{ $yA_Dni }}mm; left:{{ $xA_Dni }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}</div>
                <div class="field" style="top:{{ $yA_Nac }}mm; left:{{ $xA_Nac }}mm;">
                    {{ $venta->customer->fecha_nac ? Carbon::parse($venta->customer->fecha_nac)->format('d-m-Y') : '' }}
                </div>

                {{-- Campos nuevos --}}
                <div class="field" style="top:{{ $yA_EstadoCivil }}mm; left:{{ $xA_EstadoCivil }}mm;">{{ $estadoCivil }}
                </div>
                <div class="field" style="top:{{ $yA_SitLab }}mm; left:{{ $xA_SitLab }}mm;">{{ $sitLab }}</div>

                {{-- Dirección 2 líneas --}}
                @if($dirL1 !== '')
                    <div class="field"
                        style="top:{{ $yA_DirL1 }}mm; left:{{ $xA_DirL1 }}mm; width:{{ $wDirL1 }}mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.05;">
                        {{ $dirL1 }}
                    </div>
                @endif
                @if($dirL2 !== '')
                    <div class="field"
                        style="top:{{ $yA_DirL2 }}mm; left:{{ $xA_DirL2 }}mm; width:{{ $wDirL2 }}mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.05;">
                        {{ $dirL2 }}
                    </div>
                @endif

                <div class="field" style="top:{{ $yA_Telefonos }}mm; left:{{ $xA_Telefonos }}mm;">{{ $telefonos }}</div>
                <div class="field" style="top:{{ $yA_Vivienda }}mm; left:{{ $xA_Vivienda }}mm;">{{ $vivienda }}</div>
                <div class="field" style="top:{{ $yA_Ingresos }}mm; left:{{ $xA_Ingresos }}mm;">{{ $ingresos }}</div>
                <div class="field" style="top:{{ $yRep }}mm; left:{{ $xRep }}mm;">{{ $repEmpleado }}</div>

                {{-- B. Artículos (ocultos en copia) --}}
                @unless($isCopia)
                    @for ($i = 0; $i < 5; $i++)
                        @php
                            $y = $yBase + $i * $row;
                            $textA = isset($colA[$i]) ? mb_strtoupper($colA[$i]->producto->nombre, 'UTF-8') : '';
                            $fsA = $descFont($textA);
                            $textB = isset($colB[$i]) ? mb_strtoupper($colB[$i]->producto->nombre, 'UTF-8') : '';
                            $fsB = $descFont($textB);
                        @endphp
                        {{-- POS A --}}
                        <div class="field" style="top:{{$y}}mm; left:{{$xPosA}}mm; width:10mm; text-align:center;">
                            {{ isset($colA[$i]) ? $i + 1 : '' }}</div>
                        {{-- DESCRIPCIÓN A --}}
                        <div class="field"
                            style="top:{{$y}}mm; left:{{$xDesA}}mm; width:{{$wDesA}}mm; overflow:hidden; font-size:{{$fsA}}pt;">{{ $textA }}
                        </div>
                        {{-- POS B --}}
                        <div class="field" style="top:{{$y}}mm; left:{{$xPosB}}mm; width:10mm; text-align:center;">
                            {{ isset($colB[$i]) ? $i + 6 : '' }}</div>
                        {{-- DESCRIPCIÓN B --}}
                        <div class="field"
                            style="top:{{$y}}mm; left:{{$xDesB}}mm; width:{{$wDesA}}mm; overflow:hidden; font-size:{{$fsB}}pt;">{{ $textB }}
                        </div>
                    @endfor
                @endunless

                {{-- C. Pagos (ocultos en copia) --}}
                @unless($isCopia)
                    <div class="field"
                        style="top:{{ $yPagoFila }}mm; left:{{ $xEntrada }}mm; width:{{ $wEntrada }}mm; text-align:center;">
                        {{ number_format((float) ($venta->entrada ?? 0), 2, ',', '.') }} €
                    </div>
                    <div class="field"
                        style="top:{{ $yPagoFila }}mm; left:{{ $xNumCuotas }}mm; width:{{ $wNumCuotas }}mm; text-align:center;">
                        {{ $venta->num_cuotas }}
                    </div>
                    <div class="field"
                        style="top:{{ $yPagoFila }}mm; left:{{ $xCuota }}mm; width:{{ $wCuota }}mm; text-align:center;">
                        {{ number_format(($venta->importe_total + $venta->monto_extra)/$venta->num_cuotas, 2, ',', '.') }} €
                    </div>
                    <div class="field"
                        style="top:{{ $yPagoFila }}mm; left:{{ $xMes1 }}mm; width:{{ $wMes1 }}mm; text-align:center;">
                        {{ $venta->mes_contr?->label() }}
                    </div>
                    <div class="field"
                        style="top:{{ $yPagoFila }}mm; left:{{ $xImporte }}mm; width:{{ $wImporte }}mm; text-align:center;">
                        {{ number_format($venta->importe_total + $venta->monto_extra, 2, ',', '.') }} €
                    </div>
                @endunless

                @php
                    $iban = preg_replace('/\s+/', '', (string) ($venta->customer->iban ?? ''));
                    $iban = $iban ? trim(chunk_split($iban, 4, ' ')) : '';
                @endphp
                <div class="field" style="top:{{ $yIban }}mm; left:{{ $xIban }}mm; width:{{ $wIban }}mm;">{{ $iban }}</div>

                <div class="field"
                    style="top:{{ $yFirmas }}mm; left:{{ $xFirmaCli }}mm; width:{{ $wFirma }}mm; text-align:center;"></div>
                <div class="field"
                    style="top:{{ $yFirmas }}mm; left:{{ $xFirmaEmp }}mm; width:{{ $wFirma }}mm; text-align:center;"></div>
            </div>

            <div class="field"
                style="top:{{ $yYear }}mm; left:{{ $xYear }}mm; width:{{ $wYear }}mm; text-align:center; transform:translateX(-50%);">
                {{ $anioContrato }}
            </div>


            {{-- Firma de la Empresa (imagen) --}}
            <img class="sig" src="{{ str_replace('\\', '/', public_path('images/FirmaEmpresa.png')) }}" alt="Firma Empresa"
                style="top: {{ $yFirmas - 18 }}mm; left: {{ $xFirmaEmp + 5 }}mm; width: 35mm; height: auto;" />
        </div>

        {{-- ================= PÁGINA 2 ================= --}}
        <div class="page">
            <img class="bg" src="{{ public_path('templates/contrato-ohana-vacio-2.png') }}" alt="Fondo P2">
            <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">
                <div class="field" style="top:{{ $yP2_Dni }}mm; left:{{ $xP2_Dni }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}</div>
            </div>
            <div class="field field --sm"
                style="top:{{ $yYear2 }}mm; left:{{ $xYear2 }}mm; width:{{ $wYear2 }}mm; text-align:center; transform:translateX(-50%);">
                {{ $anioContrato2 }}
            </div>

        </div>

        {{-- ================= PÁGINA 3 – ALBARÁN PAG-1 ================= --}}
        <div class="page">
            <img class="bg" src="{{ str_replace('\\', '/', public_path('templates/ALBARAN-PAG-1.jpg')) }}" alt="Fondo P3">
            <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">

                <div class="field" style="top:{{ $yAlbContrato }}mm; left:{{ $xAlbContrato }}mm;">
                    {{ $contratoFmt }}</div>
                <div class="field" style="top:{{ $yAlbNombre }}mm; left:{{ $xAlbNombre }}mm;">
                    {{ ucwords(trim(($venta->customer->first_names ?? '') . ' ' . ($venta->customer->last_names ?? ''))) }}
                </div>
                <div class="field" style="top:{{ $yAlbDni }}mm; left:{{ $xAlbDni }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}</div>
                <div class="field" style="top:{{ $yAlbTelf }}mm; left:{{ $xAlbTelf }}mm;">{{ $telefonos }}</div>
                <div class="field"
                    style="top:{{ $yAlbDir }}mm; left:{{ $xAlbDir }}mm; width:{{ $wAlbDir }}mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ $dirOneLine }}
                </div>

                {{-- Lista de productos (oculta en copia) --}}
                @unless($isCopia)
                    @for ($i = 0; $i < $itemsAlb->count(); $i++)
                        @php
                            $y = $yAlbBase + $i * $rowAlb;
                            $txt = mb_strtoupper($itemsAlb[$i]->producto->nombre ?? '', 'UTF-8');
                            $fs = $descFont($txt);
                        @endphp
                        <div class="field" style="top:{{ $y }}mm; left:{{ $xAlbPos }}mm; width:10mm; text-align:center;">
                            {{ $i + 1 }}</div>
                        <div class="field"
                            style="top:{{ $y }}mm; left:{{ $xAlbDesc }}mm; width:{{ $wAlbDesc }}mm; overflow:hidden; font-size:{{ $fs }}pt;">
                            {{ $txt }}
                        </div>
                    @endfor
                @endunless
            </div>

        </div>

        {{-- ================= PÁGINA 4 – ALBARÁN PAG-2 ================= --}}
        <div class="page">
            <img class="bg" src="{{ str_replace('\\', '/', public_path('templates/ALBARAN-PAG-2.jpg')) }}" alt="Fondo P4">
            <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">
                <div class="field" style="top:{{ $yB2_Contrato }}mm; left:{{ $xB2_Contrato }}mm;">
                    {{ $contratoFmt }}</div>
                <div class="field" style="top:{{ $yB2_Nombre }}mm; left:{{ $xB2_Nombre }}mm;">
                    {{ ucwords(trim(($venta->customer->first_names ?? '') . ' ' . ($venta->customer->last_names ?? ''))) }}
                </div>
                <div class="field" style="top:{{ $yB2_DNI_1 }}mm; left:{{ $xB2_DNI_1 }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}</div>
                <div class="field" style="top:{{ $yB2_Telf }}mm; left:{{ $xB2_Telf }}mm;">{{ $telefonos }}</div>
                <div class="field"
                    style="top:{{ $yB2_Dir }}mm; left:{{ $xB2_Dir }}mm; width:{{ $wB2_Dir }}mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ $dirOneLine }}
                </div>
                <div class="field" style="top:{{ $yB2_DNI_2 }}mm; left:{{ $xB2_DNI_2 }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}</div>
            </div>

        </div>

        {{-- ================= PÁGINA 5 – APERTURA / DESEMBALAJE ================= --}}
        <div class="page">
            <img class="bg" src="{{ str_replace('\\', '/', public_path('templates/Apertura_de_productos.jpg')) }}"
                alt="Fondo Apertura">
            <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">
                <div class="field" style="top:{{ $yAp_Contrato }}mm; left:{{ $xApContrato }}mm;">{{ $contratoFmt }}
                </div>
                <div class="field" style="top:{{ $yAp_Nombre }}mm; left:{{ $xApNombre }}mm;">
                    {{ ucwords(trim(($venta->customer->first_names ?? '') . ' ' . ($venta->customer->last_names ?? ''))) }}
                </div>
                <div class="field" style="top:{{ $yAp_Dni1 }}mm; left:{{ $xApDni1 }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}</div>
                <div class="field"
                    style="top:{{ $yAp_Dir }}mm; left:{{ $xApDir }}mm; width:{{ $wApDir }}mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ $dirOneLine }}
                </div>
                <div class="field" style="top:{{ $yAp_Tel }}mm; left:{{ $xApTel }}mm;">{{ $telefonos }}</div>
                <div class="field" style="top:{{ $yAp_Dni2 }}mm; left:{{ $xApDni2 }}mm;">
                    {{ strtoupper($venta->customer->dni ?? '') }}
                </div>
                <div class="field field --sm"
                style="top:{{ $yYear3 }}mm; left:{{ $xYear3 }}mm; width:{{ $wYear3 }}mm; text-align:center; transform:translateX(-50%);">
                {{ $anioContrato3 }}
                </div>
            </div>

        </div>

    @endforeach

</body>

</html>