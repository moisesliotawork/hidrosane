<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Lista a mano — {{ $periodoLabel }}</title>
    <style>
        @page { margin: 18px 16px; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #111;
        }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { color: #555; margin: 0 0 12px 0; font-size: 8.5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            border: 1px solid #d1d5db;
            padding: 3px 4px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .cliente { font-weight: bold; }
        .detalle { color: #374151; }
        .obs { color: #b45309; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Lista a mano</h1>
    <p class="meta">
        Periodo: <strong>{{ $periodoLabel }}</strong>
        @if (filled($clienteQ ?? null))
            · Cliente: <strong>{{ $clienteQ }}</strong>
        @endif
        · Generado: {{ $fechaReporte }}
        · Registros: {{ $rows->count() }}
    </p>

    <table>
        <thead>
            <tr>
                <th style="width:4%">ID</th>
                <th style="width:7%">Mes</th>
                <th style="width:18%">Cliente</th>
                <th style="width:12%">Comerciales</th>
                <th style="width:4%">Nº</th>
                <th style="width:4%">Pág</th>
                <th style="width:33%">Detalle</th>
                <th style="width:18%">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->mes_codigo }}</td>
                    <td class="cliente">{{ $row->cliente }}</td>
                    <td>
                        {{ collect([$row->comercial_1, $row->comercial_2])->filter()->implode(' · ') ?: '—' }}
                    </td>
                    <td>{{ $row->nro ?? '—' }}</td>
                    <td>{{ $row->pagina ?? '—' }}</td>
                    <td class="detalle">{{ $row->detalle ?: '—' }}</td>
                    <td class="obs">{{ $row->observaciones ?: '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No hay registros para este filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
