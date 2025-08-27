@php
    use Carbon\Carbon;

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

    // ===== Coordenadas fijas en mm (las existentes NO se tocan) =====
    $yCodContrato = 11.5;
    $xCodContrato = 134.5;
    $yFecPromo = 11.5;
    $xFecPromo = 171.4;
    $yFecEntr = 16;
    $xFecEntr = 166.5;
    $yHoraEntr = 20;
    $xHoraEntr = 168.3;
    $yCodCliente = 15.6;
    $xCodCliente = 131.5;
    $yComercial = 24.3;
    $xComercial = 121.0;

    $yA_Nombre = 47.3;
    $xA_Nombre = 45;
    $yA_Dni = 51.2;
    $xA_Dni = 25.5;
    $yA_Nac = 55.6;
    $xA_Nac = 42.5;
    $yA_Dir = 47;
    $xA_Dir = 129.5;

    // ===== NUEVOS CAMPOS (coordenadas sugeridas) =====
    // Izquierda
    $yA_EstadoCivil = 59.8;
    $xA_EstadoCivil = 42.5;   // “Estado civil:”
    $yA_SitLab = 64.0;
    $xA_SitLab = 42.5;   // “Situación laboral:”
    // Derecha
    $yA_Telefonos = 55.4;
    $xA_Telefonos = 129.5;  // “Teléfonos:”

    $yA_Vivienda = 59.8;
    $xA_Vivienda = 129.5;  // “Vivienda:”

    $yA_Ingresos = 64.1;
    $xA_Ingresos = 129.5;  // “Ingresos:”

    $yBase = 93.1;     // origen tabla artículos
    $xPosA = 15.0;
    $xDesA = 32.0;
    $xPosB = 111.0;
    $xDesB = 130.0;

    $yPagoFila = 150.5;
    $xNumCuotas = 52.4;
    $wNumCuotas = 30;
    $xCuota = 90.8;
    $wCuota = 28.0;
    $xMes1 = 129.2;
    $wMes1 = 28.0;
    $xImporte = 167.6;
    $wImporte = 25.0;

    $yIban = 158.4;
    $xIban = 88.8;
    $wIban = 110.0;

    $yFirmas = 266;
    $xFirmaCli = 10.0;
    $xFirmaEmp = 130.0;
    $wFirma = 70.0;

    // Lugar/fecha desglosado (si lo usas más adelante)
    $yLugarLinea = 282.2;
    $xLugarCiudad = 15.5;
    $wLugarCiudad = 65.0;
    $xLugarDia = 47;
    $wLugarDia = 12.0;
    $xLugarMes = 76.0;
    $wLugarMes = 42.0;

    // ===== Valores formateados para nuevos campos =====
    $estadoCivil = mb_strtoupper($venta->customer->estado_civil ?? '', 'UTF-8');
    $sitLab = mb_strtoupper($venta->customer->situacion_laboral ?? '', 'UTF-8');

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
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10.5pt;
            line-height: 1;
            white-space: nowrap;
        }

        .field--sm {
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
            <div class="field" style="top:{{ $yCodCliente }}mm; left:{{ $xCodCliente }}mm;">{{ $venta->customer->id }}
            </div>
            <div class="field" style="top:{{ $yComercial }}mm; left:{{ $xComercial }}mm;">
                {{ $venta->comercial->empleado_id ?? '' }}
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
            <div class="field" style="top:{{ $yA_Dir }}mm; left:{{ $xA_Dir }}mm;">
                {{ strtoupper($venta->customer->primary_address ?? '') }}
            </div>
            <div class="field" style="top:{{ $yA_Telefonos }}mm; left:{{ $xA_Telefonos }}mm;">{{ $telefonos }}</div>
            <div class="field" style="top:{{ $yA_Vivienda }}mm; left:{{ $xA_Vivienda }}mm;">{{ $vivienda }}</div>
            <div class="field" style="top:{{ $yA_Ingresos }}mm; left:{{ $xA_Ingresos }}mm;">{{ $ingresos }}</div>

            {{-- B. Artículos (duplicados por cantidad, sin columna CANT) --}}
            @for ($i = 0; $i < 5; $i++)
                @php $y = $yBase + $i * $row; @endphp
                <div class="field" style="top:{{ $y }}mm; left:{{ $xPosA }}mm; width:10mm; text-align:center;">
                    {{ isset($colA[$i]) ? $i + 1 : '' }}
                </div>
                <div class="field" style="top:{{ $y }}mm; left:{{ $xDesA }}mm; width:60mm;">
                    {{ isset($colA[$i]) ? strtoupper($colA[$i]->producto->nombre) : '' }}
                </div>
                <div class="field" style="top:{{ $y }}mm; left:{{ $xPosB }}mm; width:10mm; text-align:center;">
                    {{ isset($colB[$i]) ? $i + 6 : '' }}
                </div>
                <div class="field" style="top:{{ $y }}mm; left:{{ $xDesB }}mm; width:60mm;">
                    {{ isset($colB[$i]) ? strtoupper($colB[$i]->producto->nombre) : '' }}
                </div>
            @endfor

            {{-- C. Pagos / IBAN / Firmas --}}
            <div class="field"
                style="top:{{ $yPagoFila }}mm; left:{{ $xNumCuotas }}mm; width:{{ $wNumCuotas }}mm; text-align:center;">
                {{ $venta->num_cuotas }}
            </div>
            <div class="field"
                style="top:{{ $yPagoFila }}mm; left:{{ $xCuota }}mm; width:{{ $wCuota }}mm; text-align:center;">
                {{ number_format($venta->cuota_mensual, 2, ',', '.') }} €
            </div>
            <div class="field"
                style="top:{{ $yPagoFila }}mm; left:{{ $xMes1 }}mm; width:{{ $wMes1 }}mm; text-align:center;">
                {{ Carbon::parse($venta->created_at)->locale('es')->addMonth()->isoFormat('MMMM') }}
            </div>
            <div class="field"
                style="top:{{ $yPagoFila }}mm; left:{{ $xImporte }}mm; width:{{ $wImporte }}mm; text-align:center;">
                {{ number_format($venta->importe_total, 2, ',', '.') }} €
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
    </div>

</body>

</html>