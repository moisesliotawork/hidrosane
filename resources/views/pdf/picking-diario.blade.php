@php
    use Illuminate\Support\Carbon;

    // $fecha puede llegar como string, array o null; lo normalizamos
    $raw = $fecha ?? null;
    if (is_array($raw)) {
        $raw = $raw['fecha'] ?? reset($raw) ?? null;
    }
    $raw = $raw ?: now()->toDateString();

    // formateo seguro
    try {
        $d = Carbon::parse($raw)->format('d-m-Y');
    } catch (\Throwable $e) {
        $d = now()->format('d-m-Y');
    }
@endphp

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Hoja de Carga - {{ $d }}</title>
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

        .meta {
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 6px 8px;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        td.num {
            text-align: right;
        }

        .checkbox {
            font-family: DejaVu Sans, Arial, sans-serif;
        }

        .footer {
            margin-top: 10px;
            font-size: 11px;
            color: #666;
        }

        .sign {
            height: 42px;
            border: 1px dashed #bbb;
        }
    </style>
</head>

<body>
    <h1>HOJA CARGA REPARTO</h1>
    <div class="meta">
        <strong>Fecha:</strong> {{ $d }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 60%">Producto</th>
                <th style="width: 15%">Cantidad</th>
                <th style="width: 25%">Entregado (Almacén)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->producto->nombre ?? ('#' . $r->producto_id) }}</td>
                    <td class="num">{{ $r->cantidad_total }}</td>
                    <td class="checkbox">
                        {{-- Caja para marcar manualmente en papel: --}}
                        @if($r->entregado) ☑ @else ☐ @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center; color:#666;">No hay registros para esta fecha.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>