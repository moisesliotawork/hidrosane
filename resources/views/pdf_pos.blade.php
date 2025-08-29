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

    // ===== NUEVOS CAMPOS (coordenadas sugeridas) =====
    // Izquierda
    $yA_EstadoCivil = 60.3;
    $xA_EstadoCivil = 31.5;   // “Estado civil:”
    $yA_SitLab = 64.7;
    $xA_SitLab = 40.1;   // “Situación laboral:”
    // Derecha
    $yA_Telefonos = 56.2;
    $xA_Telefonos = 129.5;  // “Teléfonos:”

    $yA_Vivienda = 60.3;
    $xA_Vivienda = 128.1;  // “Vivienda:”

    $yA_Ingresos = 64.6;
    $xA_Ingresos = 128.5;  // “Ingresos:”

    $yBase = 94.1;     // origen tabla artículos
    $xPosA = 15.0;
    $xDesA = 32.0;
    $xPosB = 111.0;
    $xDesB = 130.0;

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

    $yIban = 159.4;
    $xIban = 88.8;
    $wIban = 110.0;

    $yFirmas = 267;
    $xFirmaCli = 11.0;
    $xFirmaEmp = 131.0;
    $wFirma = 70.0;

    // Lugar/fecha desglosado (si lo usas más adelante)
    $yLugarLinea = 282.2;
    $xLugarCiudad = 15.5;
    $wLugarCiudad = 65.0;
    $xLugarDia = 47;
    $wLugarDia = 12.0;
    $xLugarMes = 76.0;
    $wLugarMes = 42.0;

    // ===== Página 2: DNI (ajusta solo si lo necesitas) =====
    $yP2_Dni = 245;   // mm
    $xP2_Dni = 45;   // mm


    // ===== Valores formateados para nuevos campos =====
    $estadoCivil = mb_strtoupper($venta->customer->estado_civil ?? '', 'UTF-8');
    // Forzar MAYÚSCULAS y conservar el "/A" usando el label del enum
    $sitLab = mb_strtoupper(
        (\App\Enums\SituacionLaboral::tryFrom($venta->customer->situacion_laboral ?? '')
                ?->label()) ?? ($venta->customer->situacion_laboral ?? ''),
        'UTF-8'
    );


    $telefonos = collect([
        $venta->customer->phone ?? null,
        $venta->customer->phone2 ?? null,
        $venta->customer->mobile ?? null,
    ])->filter()->implode(' / ');

    $vivienda = mb_strtoupper($venta->customer->tipo_vivienda ?? '', 'UTF-8');
    $ingresos = mb_strtoupper($venta->customer->ingresos_rango ?? '', 'UTF-8');

    // Lugar (si lo necesitas)
    $lugarCiudad = mb_strtoupper($venta->customer->postalCode?->city?->title ?? 'VIGO', 'UTF-8');
    $lugarDia = now()->format('d');
    $lugarMes = mb_strtoupper(now()->locale('es')->isoFormat('MMMM'), 'UTF-8');


    // ===== Dirección en 2 líneas con auto-shrink =====
    $dirRaw = mb_strtoupper($venta->customer->primary_address ?? '', 'UTF-8');

    // Límite de “anchura” medido en caracteres aprox. (ajusta si ves que cabe más)
    $DIR_MAX = 46;

    [$dirL1, $dirL2, $dirFS] = (function (string $text, int $max) {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '')
            return ['', '', 11];

        $words = preg_split('/\s+/u', $text);
        $acc = '';
        $best1 = '';
        $best2 = '';

        // Buscamos la división que deje la 1ª línea lo más llena posible
        // y la 2ª <= $max si es posible.
        for ($i = 0; $i < count($words); $i++) {
            $cand1 = trim($acc . ($acc === '' ? '' : ' ') . $words[$i]);
            $rest = implode(' ', array_slice($words, $i + 1));
            if (mb_strlen($cand1, 'UTF-8') <= $max && mb_strlen($rest, 'UTF-8') <= $max) {
                $best1 = $cand1;
                $best2 = $rest;
            }
            $acc = $cand1;
        }

        // Si no encontramos split “perfecto”, partimos a la fuerza en $max chars.
        if ($best1 === '') {
            $best1 = trim(mb_substr($text, 0, $max, 'UTF-8'));
            $best2 = trim(mb_substr($text, mb_strlen($best1, 'UTF-8'), null, 'UTF-8'));
        }

        // Tamaño de fuente solo para dirección (auto-shrink si la 2ª se pasa)
        // Auto-shrink si CUALQUIERA de las dos líneas se pasa
        // Auto-shrink si CUALQUIERA de las dos líneas o algún "chunk" sin separadores se pasa
        $fs = 7;
        $len1 = mb_strlen($best1, 'UTF-8');
        $len2 = mb_strlen($best2, 'UTF-8');
        $over = max($len1, $len2);

        // penaliza trozos sin separadores (p.ej., 'PESNCPAWENCNAPI...')
        $chunkLen = function (string $s): int{
            $parts = preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
            return $parts ? max(array_map(fn($w) => mb_strlen($w, 'UTF-8'), $parts)) : 0;
        };
        $over = max($over, $chunkLen($best1), $chunkLen($best2));

        if ($over > $max)
            $fs = 10;
        if ($over > $max + 8)
            $fs = 9;
        if ($over > $max + 16)
            $fs = 8;


        return [$best1, $best2, $fs];
    })($dirRaw, $DIR_MAX);

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
            /* ⬅️ aquí */
            font-size: 11pt;
            line-height: 1;
            white-space: nowrap;
        }

        .field--sm {
            font-family: Helvetica, Arial, sans-serif;
            /* ⬅️ y aquí */
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

    <div class="page">
        <img class="bg" src="{{ str_replace('\\', '/', public_path('templates/contrato-ohana-vacio-1.png')) }}"
            alt="Fondo P1">

        <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">

            {{-- Encabezado --}}
            <div class="field" style="top:{{ $yCodContrato }}mm; left:{{ $xCodContrato }}mm;">{{ $venta->nro_contrato }}
            </div>
            <div class="field" style="top:{{ $yFecPromo }}mm; left:{{ $xFecPromo }}mm;">{{ $fecPromo }}</div>
            <div class="field" style="top:{{ $yFecEntr }}mm; left:{{ $xFecEntr }}mm;">{{ $fecEntr }}</div>
            <div class="field" style="top:{{ $yHoraEntr }}mm; left:{{ $xHoraEntr }}mm;">
                {{ strtoupper($venta->horario_entrega ?? '') }}
            </div>
            <div class="field" style="top:{{ $yCodCliente }}mm; left:{{ $xCodCliente }}mm;">
                {{ $venta->customer->nro_cliente }}
            </div>
            <div class="field" style="top:{{ $yComercial }}mm; left:{{ $xComercial }}mm;">
                @php
                    $codCom = $venta->comercial->empleado_id ?? '';
                    $codComp = $venta->companion_id ? (optional($venta->companion)->empleado_id ?? $venta->companion_id) : null;
                @endphp
                {{ $codComp ? ($codCom . ' - ' . $codComp) : $codCom }}
            </div>


            {{-- A. Datos personales --}}
            <div class="field" style="top:{{ $yA_Nombre }}mm; left:{{ $xA_Nombre }}mm;">
                {{ ucwords(trim(($venta->customer->first_names ?? '') . ' ' . ($venta->customer->last_names ?? ''))) }}
            </div>
            <div class="field" style="top:{{ $yA_Dni }}mm; left:{{ $xA_Dni }}mm;">
                {{ strtoupper($venta->customer->dni ?? '') }}
            </div>
            <div class="field" style="top:{{ $yA_Nac }}mm; left:{{ $xA_Nac }}mm;">
                {{ $venta->customer->fecha_nac ? Carbon::parse($venta->customer->fecha_nac)->format('d-m-Y') : '' }}
            </div>

            {{-- NUEVOS: Estado civil + Situación laboral (izquierda) --}}
            <div class="field" style="top:{{ $yA_EstadoCivil }}mm; left:{{ $xA_EstadoCivil }}mm;">{{ $estadoCivil }}
            </div>
            <div class="field" style="top:{{ $yA_SitLab }}mm; left:{{ $xA_SitLab }}mm;">{{ $sitLab }}</div>

            {{-- Domicilio + Teléfonos + Vivienda + Ingresos (derecha) --}}
            <div class="field" style="top:{{ $yA_Dir }}mm; left:{{ $xA_Dir }}mm; width:75mm;
    white-space:normal; line-height:1.05; font-size:{{ $dirFS }}pt;
    overflow-wrap:anywhere; word-break:break-word;
    max-height: calc(2 * 1.05em); overflow: hidden;">
                @if($dirL2 !== '')
                    {{ $dirL1 }}<br>{{ $dirL2 }}
                @else
                    {{ $dirL1 }}
                @endif
            </div>

            <div class="field" style="top:{{ $yA_Telefonos }}mm; left:{{ $xA_Telefonos }}mm;">{{ $telefonos }}</div>
            <div class="field" style="top:{{ $yA_Vivienda }}mm; left:{{ $xA_Vivienda }}mm;">{{ $vivienda }}</div>
            <div class="field" style="top:{{ $yA_Ingresos }}mm; left:{{ $xA_Ingresos }}mm;">{{ $ingresos }}</div>

            <div class="field" style="top:{{ $yRep }}mm; left:{{ $xRep }}mm;">
                {{ $repEmpleado }}
            </div>

            {{-- B. Artículos (duplicados por cantidad, sin columna CANT) --}}
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
                    {{ isset($colA[$i]) ? $i + 1 : '' }}
                </div>
                {{-- DESCRIPCIÓN A (auto-shrink) --}}
                <div class="field"
                    style="top:{{$y}}mm; left:{{$xDesA}}mm; width:60mm; overflow:hidden; font-size:{{$fsA}}pt;">
                    {{ $textA }}
                </div>

                {{-- POS B --}}
                <div class="field" style="top:{{$y}}mm; left:{{$xPosB}}mm; width:10mm; text-align:center;">
                    {{ isset($colB[$i]) ? $i + 6 : '' }}
                </div>
                {{-- DESCRIPCIÓN B (auto-shrink) --}}
                <div class="field"
                    style="top:{{$y}}mm; left:{{$xDesB}}mm; width:60mm; overflow:hidden; font-size:{{$fsB}}pt;">
                    {{ $textB }}
                </div>
            @endfor

            {{-- C. Pagos / IBAN / Firmas --}}
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
                {{ number_format($venta->cuota_final, 2, ',', '.') }} €
            </div>
            <div class="field"
                style="top:{{ $yPagoFila }}mm; left:{{ $xMes1 }}mm; width:{{ $wMes1 }}mm; text-align:center;">
                {{ mb_strtoupper(
    Carbon::parse($venta->created_at)
        ->locale('es')
        ->addMonth()
        ->isoFormat('MMMM'),
    'UTF-8'
) 
                }}
            </div>
            <div class="field"
                style="top:{{ $yPagoFila }}mm; left:{{ $xImporte }}mm; width:{{ $wImporte }}mm; text-align:center;">
                {{ number_format($venta->total_final, 2, ',', '.') }} €
            </div>

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

        {{-- Firma de la Empresa (imagen) --}}
        <img class="sig" src="{{ str_replace('\\', '/', public_path('images/FirmaEmpresa.png')) }}" alt="Firma Empresa"
            style="
        top: {{ $yFirmas - 18 }}mm;     /* sube la imagen para que quede sobre la línea */
        left: {{ $xFirmaEmp + 5 }}mm;   /* un pequeño margen desde el inicio del recuadro */
        width: 35mm;                    /* ocupa buena parte del ancho del recuadro ($wFirma=70) */
        height: auto;
    " />


        @if($debug)
            <div class="grid">
                @for($y = 0; $y <= 297; $y += 5)
                <div class="h" style="top:{{ $y }}mm"></div>@endfor
                @for($x = 0; $x <= 210; $x += 5)
                <div class="v" style="left:{{ $x }}mm"></div>@endfor
            </div>
        @endif
    </div>

    {{-- Página 2 (si aplica) --}}
    <div class="page">
        <img class="bg" src="{{ public_path('templates/contrato-ohana-vacio-2.png') }}" alt="Fondo P2">

        {{-- Overlay de la P2 (mismo dx/dy/scale) --}}
        <div class="surface" style="transform: translate({{ $dx }}mm, {{ $dy }}mm) scale({{ $sx }}, {{ $sy }});">
            <div class="field" style="top:{{ $yP2_Dni }}mm; left:{{ $xP2_Dni }}mm;">
                {{ strtoupper($venta->customer->dni ?? '') }}
            </div>
        </div>
    </div>

</body>

</html>