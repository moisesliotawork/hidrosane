<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recuperados aceptados</title>
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
        .mes { font-weight: bold; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Recuperados aceptados</h1>
    <p class="meta">
        Generado: {{ $fechaReporte }} · Registros: {{ $rows->count() }}
    </p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>DNI</th>
                <th># Contrato</th>
                <th>Fecha/Contrato</th>
                <th>Mes</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>ID Vta</th>
                <th>Docs</th>
                <th>Aceptado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $fechaRaw = data_get($row->reviewed_json, 'fecha_venta') ?? $row->venta?->fecha_venta;
                    $fecha = null;
                    try {
                        if (filled($fechaRaw)) {
                            $fecha = \Illuminate\Support\Carbon::parse($fechaRaw)->timezone('Europe/Madrid');
                        }
                    } catch (\Throwable) {
                        $fecha = null;
                    }
                    $mesLabels = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SEP',10=>'OCT',11=>'NOV',12=>'DIC'];
                    $mesHex = [1=>'#9f1239',2=>'#9d174d',3=>'#6b21a8',4=>'#5b21b6',5=>'#3730a3',6=>'#075985',7=>'#115e59',8=>'#065f46',9=>'#3f6212',10=>'#854d0e',11=>'#9a3412',12=>'#991b1b'];
                    $mesLabel = $fecha ? (($mesLabels[(int)$fecha->month] ?? '').' '.$fecha->format('y')) : '—';
                    $mesColor = $fecha ? ($mesHex[(int)$fecha->month] ?? '#6b7280') : '#6b7280';
                    $dniRaw = mb_strtoupper(preg_replace('/[^0-9A-Z]/', '', (string) ($row->dni ?? '')) ?? '');
                    $dniLetter = '';
                    if ($dniRaw !== '' && preg_match('/[A-Z]$/', $dniRaw) === 1) {
                        $dniLetter = substr($dniRaw, -1);
                        $dniRaw = substr($dniRaw, 0, -1);
                    }
                    $dniFmt = $dniRaw === '' && $dniLetter === ''
                        ? '—'
                        : trim(implode(' ', $dniRaw === '' ? [] : str_split($dniRaw, 4)).($dniLetter !== '' ? ' '.$dniLetter : ''));
                @endphp
                <tr>
                    <td>{{ $row->id }}</td>
                    <td style="font-weight:bold;color:#b45309;">{{ $dniFmt }}</td>
                    <td>{{ $row->nro_contr_adm ?: '—' }}</td>
                    <td class="fecha">{{ $fecha?->format('d/m/Y') ?? '—' }}</td>
                    <td class="mes" style="color: {{ $mesColor }};">{{ $mesLabel }}</td>
                    <td class="nombre">{{ $row->cliente_nombre ?: '—' }}</td>
                    <td>{{ $row->statusLabel() }}</td>
                    <td>{{ $row->venta_id ?: '—' }}</td>
                    <td>{{ count($row->documents ?? []) }}</td>
                    <td>{{ optional($row->created_at)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No hay recuperados aceptados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
