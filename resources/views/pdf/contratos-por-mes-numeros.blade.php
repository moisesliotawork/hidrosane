<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Nº contratos admin por mes</title>
    <style>
        @page {
            margin: 18px 16px;
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

        h2 {
            font-size: 12px;
            margin: 12px 0 4px 0;
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

        .grupo {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .count {
            color: #444;
            font-weight: normal;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 3px 4px;
            text-align: left;
            font-size: 8px;
            width: 12.5%;
            word-wrap: break-word;
        }

        th {
            background: #f0f0f0;
            text-transform: uppercase;
            font-size: 7px;
        }

        td {
            font-weight: bold;
        }

        th.sep,
        td.sep {
            border-left-width: 2px;
            border-left-color: #555;
        }

        .empty {
            color: #666;
        }
    </style>
</head>

<body>
    <div class="meta">FECHA DE ESTE REPORTE: {{ $fechaReporte }}</div>
    <h1>Nº contratos admin por mes</h1>
    <div class="submeta">Periodo del reporte: {{ $periodoLabel }}</div>

    @forelse ($grupos as $grupo)
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
        <div class="grupo">
            <h2>
                {{ \App\Support\ContratosPorMesStats::labelForMonthKey((string) $grupo->mes_key) }}
                <span class="count">({{ $grupo->total }})</span>
            </h2>
            <table>
                <thead>
                    <tr>
                        @for ($c = 0; $c < $cols; $c++)
                            <th @if ($c > 0) class="sep" @endif># Registro</th>
                            <th># Contrato_admin</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $filas; $i++)
                        <tr>
                            @for ($c = 0; $c < $cols; $c++)
                                @php $item = $columnas[$c]->get($i); @endphp
                                <td @if ($c > 0) class="sep" @endif>{{ $item->id ?? '' }}</td>
                                <td>{{ $item->nro_contr_adm ?? '' }}</td>
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @empty
        <p class="empty">No hay números de contrato admin para este periodo.</p>
    @endforelse
</body>

</html>
