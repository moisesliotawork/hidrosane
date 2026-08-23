<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $scopeLabel ?? 'Recuperados' }}</title>
    <style>
        @page { margin: 16px 14px; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #111;
        }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { color: #555; margin: 0 0 10px 0; font-size: 8.5px; }
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
        }
        .nombre { font-weight: bold; text-transform: uppercase; }
        .fecha { font-weight: bold; color: #b45309; }
        .ofertas { font-size: 7.5px; line-height: 1.25; }
        .ofertas-line { display: block; white-space: nowrap; }
        .ofertas-asignar { color: #dc2626; font-weight: bold; }
        .estado-pend { color: #b45309; font-weight: bold; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $scopeLabel ?? 'Recuperados' }}</h1>
    <p class="meta">
        Periodo: <strong>{{ $periodoLabel ?? 'Todos' }}</strong>
        · Generado: {{ $fechaReporte }} · Registros: {{ $rows->count() }}
    </p>

    <table>
        <thead>
            <tr>
                <th># Contrato</th>
                <th>Cliente</th>
                <th>DNI</th>
                <th>Fecha/Contrato</th>
                <th>OfertasDeLaVenta</th>
                <th>Estado</th>
                <th>ID Vta</th>
                <th>Docs</th>
                <th>ID</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $fechaRaw = $row->displayFechaVentaRaw();
                    $fecha = null;
                    try {
                        if (filled($fechaRaw)) {
                            $fecha = $fechaRaw instanceof \Illuminate\Support\Carbon
                                ? $fechaRaw->copy()->timezone('Europe/Madrid')
                                : \Illuminate\Support\Carbon::parse($fechaRaw)->timezone('Europe/Madrid');
                        }
                    } catch (\Throwable) {
                        $fecha = null;
                    }
                    $dniRaw = mb_strtoupper(preg_replace('/[^0-9A-Z]/', '', (string) ($row->displayDni() ?? '')) ?? '');
                    $dniLetter = '';
                    if ($dniRaw !== '' && preg_match('/[A-Z]$/', $dniRaw) === 1) {
                        $dniLetter = substr($dniRaw, -1);
                        $dniRaw = substr($dniRaw, 0, -1);
                    }
                    $dniFmt = $dniRaw === '' && $dniLetter === ''
                        ? '—'
                        : trim(implode(' ', $dniRaw === '' ? [] : str_split($dniRaw, 4)).($dniLetter !== '' ? ' '.$dniLetter : ''));

                    $ofertaNombres = $row->displayOfertaNombres();
                    $estadoLabel = $row->statusLabel();
                    $isPend = $row->status === \App\Models\ContratoRecoveryItem::STATUS_PENDING_ADD;
                    $porAsignar = \App\Services\ContractRecovery\ContractFromImageRecovery::OFERTA_POR_ASIGNAR_NOMBRE;
                @endphp
                <tr>
                    <td>{{ $row->displayNroContrAdm() ?: '—' }}</td>
                    <td class="nombre">{{ $row->displayClienteNombre() ?: '—' }}</td>
                    <td style="font-weight:bold;color:#b45309;">{{ $dniFmt }}</td>
                    <td class="fecha">{{ $fecha?->format('d/m/Y') ?? '—' }}</td>
                    <td class="ofertas">
                        @forelse ($ofertaNombres as $ofertaNombre)
                            <span class="ofertas-line {{ $ofertaNombre === $porAsignar ? 'ofertas-asignar' : '' }}">{{ $ofertaNombre }}</span>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td class="{{ $isPend ? 'estado-pend' : '' }}">{{ $estadoLabel }}</td>
                    <td>{{ $row->venta_id ?: '—' }}</td>
                    <td>{{ $row->displayDocsCount() }}</td>
                    <td>{{ $row->id }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No hay recuperados aceptados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
