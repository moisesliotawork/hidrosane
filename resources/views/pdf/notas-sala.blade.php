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

        .subgrid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .subgrid thead th {
            text-align: left;
            font-size: 10px;
            color: #6b7280;
            padding: 6px 8px;
            background: #f7f7f7;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .subgrid tbody td {
            padding: 6px 8px;
            font-size: 12px;
            vertical-align: top;
            border-top: 1px solid #f0f0f0;
        }

        .subgrid .empty {
            color: #777;
            font-style: italic;
        }

        .section-title {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-top: 8px;
        }

        .subgrid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .subgrid thead th {
            text-align: left;
            font-size: 10px;
            color: #6b7280;
            padding: 6px 8px;
            background: #f7f7f7;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .subgrid tbody td {
            padding: 6px 8px;
            font-size: 12px;
            vertical-align: top;
            border-top: 1px solid #f0f0f0;
        }

        .subgrid .empty {
            color: #777;
            font-style: italic;
        }

        .section-title {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-top: 12px;
            margin-bottom: 4px;
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

            @php
                /** ===== JSON: Tel Op. / Jefe de Sala ===== */
                $jsonRaw = $note->getAttribute('observations'); // atributo JSON, NO relación
                if (is_string($jsonRaw)) {
                    $decoded = json_decode($jsonRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE)
                        $jsonRaw = $decoded;
                }

                $jsonRows = [];
                if (is_array($jsonRaw)) {
                    foreach ($jsonRaw as $row) {
                        // Texto
                        $txt = is_array($row) ? ($row['observation'] ?? null) : $row;
                        $txt = trim((string) $txt);

                        // Autor (si se guardó author_id en el JSON)
                        $authorName = null;
                        if (is_array($row) && !empty($row['author_id'])) {
                            $u = \App\Models\User::find($row['author_id']);
                            if ($u) {
                                // Ej: "E123 - Nombre Apellido" si hay empleado_id
                                $authorName = trim(($u->empleado_id ? ($u->empleado_id . ' - ') : '') . ($u->name . ' ' . $u->last_name));
                            }
                        }

                        // Fecha (si existe en el JSON). Muchos JSON no tienen fecha; se deja vacío.
                        $fechaStr = null;
                        if (is_array($row) && !empty($row['created_at'])) {
                            try {
                                $fechaStr = \Illuminate\Support\Carbon::parse($row['created_at'])->format('d/m/Y H:i');
                            } catch (\Throwable $e) { /* silencioso */
                            }
                        }

                        if ($txt !== '') {
                            $jsonRows[] = [
                                'fecha' => $fechaStr,
                                'autor' => $authorName,
                                'detalle' => $txt,
                            ];
                        }
                    }
                }

                /** ===== Comercial: relación observations (con author) ===== */
                if ($note->relationLoaded('observations')) {
                    $rels = $note->getRelation('observations');
                } else {
                    $rels = $note->observations()->with('author')->get();
                }

                $comRows = $rels->sortBy('created_at')->map(function ($o) {
                    $autor = null;
                    if ($o->author) {
                        $autor = trim(($o->author->empleado_id ? ($o->author->empleado_id . ' - ') : '')
                            . ($o->author->name . ' ' . $o->author->last_name));
                    }
                    return [
                        'fecha' => $o->created_at ? $o->created_at->format('d/m/Y H:i') : null,
                        'autor' => $autor,
                        'detalle' => trim((string) ($o->observation ?? '')),
                    ];
                })->filter(fn($r) => $r['detalle'] !== '')->values()->all();
            @endphp

            {{-- ========= TABLA: Tel Op. / Jefe de Sala (JSON) ========= --}}
            <div class="section-title">Observaciones Tel Op. / Jefe de Sala</div>
            <table class="subgrid">
                <thead>
                    <tr>
                        <th style="width:22%">Fecha</th>
                        <th style="width:28%">Autor</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @if(empty($jsonRows))
                        <tr>
                            <td class="empty" colspan="3">No existen observaciones de teleoperadora o jefe de sala.</td>
                        </tr>
                    @else
                        @foreach($jsonRows as $r)
                            <tr>
                                <td>{{ $r['fecha'] ?? '—' }}</td>
                                <td>{{ $r['autor'] ?? '—' }}</td>
                                <td>• {{ $r['detalle'] }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            {{-- separador visual --}}
            <div style="height:10px;"></div>

            {{-- ========= TABLA: Observaciones Comercial (relación) ========= --}}
            <div class="section-title">Observaciones Comercial</div>
            <table class="subgrid">
                <thead>
                    <tr>
                        <th style="width:22%">Fecha</th>
                        <th style="width:28%">Autor</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @if(empty($comRows))
                        <tr>
                            <td class="empty" colspan="3">No existen observaciones de comerciales.</td>
                        </tr>
                    @else
                        @foreach($comRows as $r)
                            <tr>
                                <td>{{ $r['fecha'] ?? '—' }}</td>
                                <td>{{ $r['autor'] ?? '—' }}</td>
                                <td>• {{ $r['detalle'] }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            <div class="footer">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    @endforeach
</body>

</html>