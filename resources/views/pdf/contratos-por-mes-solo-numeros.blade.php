<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Solo números de contratos</title>
    <style>
        @page {
            margin: 18px 14px;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 6px 0;
        }

        .meta {
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 11px;
        }

        .submeta {
            margin: -2px 0 10px 0;
            color: #444;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            border: 1px solid #999;
            padding: 3px 2px;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            width: 10%;
            word-wrap: break-word;
        }

        .empty {
            color: #666;
        }
    </style>
</head>

<body>
    <div class="meta">FECHA DE ESTE REPORTE: {{ $fechaReporte }}</div>
    <h1>SOLO NUMEROS DE CONTRATOS</h1>
    <div class="submeta">Periodo del reporte: {{ $periodoLabel }} ({{ count($numeros) }})</div>

    @if (count($numeros) === 0)
        <p class="empty">No hay números de contrato admin para este periodo.</p>
    @else
        @php
            $nums = collect($numeros)->values();
            $cols = 10;
            $porCol = (int) ceil(max(1, $nums->count()) / $cols);
            $columnas = [];
            for ($c = 0; $c < $cols; $c++) {
                $columnas[$c] = $nums->slice($c * $porCol, $porCol)->values();
            }
            $filas = max(1, ...array_map(fn ($col) => $col->count(), $columnas));
        @endphp
        <table>
            <tbody>
                @for ($i = 0; $i < $filas; $i++)
                    <tr>
                        @for ($c = 0; $c < $cols; $c++)
                            <td>{{ $columnas[$c]->get($i) ?? '' }}</td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    @endif
</body>

</html>
