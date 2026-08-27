<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Contratos/MES</title>
    <style>
        @page {
            margin: 24px 24px;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #111;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 8px 0;
        }

        h2 {
            font-size: 14px;
            margin: 18px 0 8px 0;
        }

        h3 {
            font-size: 12px;
            margin: 12px 0 6px 0;
        }

        .meta {
            margin-bottom: 14px;
            color: #111;
            font-weight: bold;
            font-size: 12px;
        }

        .submeta {
            margin: -8px 0 14px 0;
            color: #444;
            font-weight: normal;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 5px 6px;
            font-size: 10px;
            vertical-align: top;
        }

        th {
            background: #f0f0f0;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        td.num,
        th.num,
        td.center,
        th.center {
            text-align: center;
        }

        td.contratos-count {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
        }

        th.contratos-count {
            text-align: center;
        }

        tfoot td {
            font-weight: bold;
            background: #f7f7f7;
        }

        tfoot td.contratos-count {
            text-align: center;
            font-size: 13px;
        }

        .down { color: #dc2626; font-weight: bold; }
        .up { color: #16a34a; font-weight: bold; }
        .same { color: #2563eb; font-weight: bold; }
        .bold { font-weight: bold; }
        .empty { color: #666; font-size: 11px; margin: 0 0 8px 0; }
    </style>
</head>

<body>
    <div class="meta">FECHA DE ESTE REPORTE: {{ $fechaReporte }}</div>

    <h1>Contratos/MES</h1>
    <div class="submeta">Periodo del reporte: {{ $periodoVariaciones }}</div>

    <h2>Resumen por mes</h2>
    <table>
        <thead>
            <tr>
                <th>Mes</th>
                <th class="contratos-count">Nº de contratos</th>
                <th class="center">HAY CAMBIO?</th>
                <th class="center">VARIACIÓN</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @forelse ($rows as $row)
                @php
                    $total = (int) $row->total;
                    $variacion = (int) $row->variacion;
                    $grandTotal += $total;
                    $label = \App\Support\ContratosPorMesStats::labelForMonthKey((string) $row->mes_key);
                    $varClass = $variacion === 0 ? 'same' : ($variacion < 0 ? 'down' : 'up');
                    // Siempre con signo explícito: +N / -N / 0
                    $varText = $variacion > 0
                        ? '+' . $variacion
                        : ($variacion < 0 ? (string) $variacion : '0');
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td class="contratos-count">{{ number_format($total, 0, ',', '.') }}</td>
                    <td class="center {{ $varClass }}">{{ $variacion === 0 ? 'NO' : 'SÍ' }}</td>
                    <td class="center {{ $varClass }}">{{ $varText }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No hay contratos con fecha de venta.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td class="contratos-count">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <h2>Variaciones de Contratos</h2>

    <h3 class="down">Contratos quitados ({{ $quitados->count() }})</h3>
    @if ($quitados->isEmpty())
        <p class="empty">No hay contratos quitados en este periodo.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>ID registro</th>
                    <th>Quién hizo variar?</th>
                    <th>Estado</th>
                    <th>Nº contrato admin</th>
                    <th>Cliente</th>
                    <th>DNI</th>
                    <th>Mes</th>
                    <th>El día</th>
                    <th>Fecha y hora</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quitados as $item)
                    <tr>
                        <td>{{ $item->venta_id ?? '—' }}</td>
                        <td class="bold">{{ $item->quienLabel() }}</td>
                        <td>{{ $item->estadoLabel() }}</td>
                        <td class="bold">{{ $item->nro_contr_adm ?? '—' }}</td>
                        <td>{{ $item->cliente_nombre ?? '—' }}</td>
                        <td class="bold">{{ $item->dni ?? '—' }}</td>
                        <td>{{ \App\Support\ContratosPorMesStats::labelForMonthKey((string) $item->mes_key) }}</td>
                        <td>{{ $item->diaLabel() }}</td>
                        <td class="bold">{{ $item->fechaHoraLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3 class="up">Contratos agregados ({{ $agregados->count() }})</h3>
    @if ($agregados->isEmpty())
        <p class="empty">No hay contratos agregados en este periodo.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>ID registro</th>
                    <th>Quién hizo variar?</th>
                    <th>Estado</th>
                    <th>Nº contrato admin</th>
                    <th>Cliente</th>
                    <th>DNI</th>
                    <th>Mes</th>
                    <th>El día</th>
                    <th>Fecha y hora</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agregados as $item)
                    <tr>
                        <td>{{ $item->venta_id ?? '—' }}</td>
                        <td class="bold">{{ $item->quienLabel() }}</td>
                        <td>{{ $item->estadoLabel() }}</td>
                        <td class="bold">{{ $item->nro_contr_adm ?? '—' }}</td>
                        <td>{{ $item->cliente_nombre ?? '—' }}</td>
                        <td class="bold">{{ $item->dni ?? '—' }}</td>
                        <td>{{ \App\Support\ContratosPorMesStats::labelForMonthKey((string) $item->mes_key) }}</td>
                        <td>{{ $item->diaLabel() }}</td>
                        <td class="bold">{{ $item->fechaHoraLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>

</html>
