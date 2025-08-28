<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Notas en SALA</title>
    <style>
        @page {
            margin: 24mm 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
        }

        .sheet {
            page-break-after: always;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
        }

        .pink {
            background: #ffd1e6;
            color: #a31164;
        }

        .gray {
            background: #eee;
            color: #333;
        }

        .green {
            background: #d6f5d6;
            color: #1b7a1b;
        }

        .muted {
            color: #666;
        }

        .title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 2px;
        }

        .sub {
            font-size: 12px;
            margin: 0;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .grid th {
            text-align: left;
            font-size: 11px;
            color: #666;
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }

        .grid td {
            padding: 8px;
            vertical-align: top;
        }

        .row {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            flex: 1;
        }

        .label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .value {
            font-size: 13px;
            font-weight: 600;
            margin-top: 2px;
        }

        .footer {
            margin-top: 12px;
            font-size: 10px;
            color: #777;
            text-align: right;
        }
    </style>
</head>

<body>
    @foreach($notes as $note)
        @php
            $c = $note->customer;
            $cp = $c?->postalCode;
            $city = $cp?->city?->title;
            $cpCode = $cp?->code;
            $user = $note->user
        @endphp

        <div class="sheet">
            <div class="header">
                <div class="brand">HOJA DE SALA</div>
                <div>
                    <span class="badge pink">SALA</span>
                    <span class="badge gray"># Nota: {{ $note->nro_nota }}</span>
                </div>
            </div>

            <div class="row">
                <div class="card">
                    <div class="label">Cliente</div>
                    <div class="value">{{ $c?->name }}</div>
                </div>
                <div class="card">
                    <div class="label">Teléfono</div>
                    <div class="value">{{ $c?->phone }}</div>
                </div>
                <div class="card">
                    <div class="label">CP / Ciudad</div>
                    <div class="value">{{ $cpCode }} {{ $city ? '— ' . $city : '' }}</div>
                </div>
            </div>

            <div class="row">
                <div class="card">
                    <div class="label">Dirección</div>
                    <div class="value">{{ $c?->primary_address }}</div>
                </div>
                <div class="card">
                    <div class="label">Ayuntamiento</div>
                    <div class="value">{{ $note->ayuntamiento }}</div>
                </div>
            </div>

            <div class="row">
                <div class="card">
                    <div class="label">Comercial</div>
                    <div class="value">
                        {{ $note->comercial?->empleado_id ? ($note->comercial?->empleado_id . ' - ') : '' }}
                        {{ $note->comercial?->name ?? 'Sin asignar' }}
                    </div>
                </div>
                <div class="card">
                    <div class="label">Tel Op</div>
                    <div class="value">
                        {{ $note->user?->empleado_id ? ($note->user->empleado_id . ' - ') : '' }}
                        {{ $note->user?->name ?? 'Sin asignar' }}
                    </div>
                </div>
                <div class="card">
                    <div class="label">Asignación</div>
                    <div class="value">{{ optional($note->assignment_date)->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div class="card">
                    <div class="label">Horario</div>
                    <div class="value">{{ $note->visit_schedule ?? '—' }}</div>
                </div>
            </div>

            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:25%">Estado</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="badge green">{{ $note->estado_terminal->label() }}</span>
                            <div class="muted">Creada: {{ optional($note->created_at)->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>
                            @php
                                $obs = is_array($note->observations) ? $note->observations : [];
                            @endphp
                            @forelse($obs as $o)
                                <div>• {{ $o['observation'] ?? '' }}</div>
                            @empty
                                <div class="muted">Sin observaciones</div>
                            @endforelse
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="footer">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    @endforeach
</body>

</html>