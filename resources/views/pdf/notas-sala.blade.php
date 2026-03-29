<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Notas en Oficina v3</title>
    <style>
        @page { margin: 15mm; }
        body { font-family: sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .sheet { page-break-after: always; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 20px; }
        .header-table { width: 100%; border-bottom: 2px solid #333; margin-bottom: 15px; }
        .brand { font-size: 18px; font-weight: bold; }
        .nro-nota { text-align: right; font-size: 14px; font-weight: bold; }

        .data-table { width: 100%; border-collapse: separate; border-spacing: 5px; margin-bottom: 10px; table-layout: fixed; }
        .card { border: 1px solid #ddd; padding: 8px; vertical-align: top; border-radius: 5px; background-color: #f9f9f9; }
        .label { font-size: 9px; color: #777; text-transform: uppercase; font-weight: bold; margin-bottom: 3px; border-bottom: 1px solid #eee; display: block; }
        .value { font-size: 12px; font-weight: bold; color: #000; word-wrap: break-word; }

        .obs-title { background: #eee; padding: 5px 10px; font-weight: bold; margin-top: 15px; border-radius: 3px; font-size: 10px; text-transform: uppercase; }
        .obs-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .obs-table th { background: #f5f5f5; text-align: left; padding: 5px; font-size: 9px; border: 1px solid #ddd; }
        .obs-table td { padding: 5px; border: 1px solid #ddd; font-size: 10px; vertical-align: top; }
        .empty { font-style: italic; color: #999; text-align: center; padding: 10px; }
        .footer { text-align: right; font-size: 9px; color: #999; margin-top: 10px; }
    </style>
</head>
<body>
    @foreach($notes as $note)
        @php
            $c = $note->customer;
            $cpCode = $c?->postal_code;
            $city = $c?->ciudad;
        @endphp

        <div class="sheet">
            <table class="header-table">
                <tr>
                    <td class="brand">ENVIADAS A OFICINA - SALA</td>
                    <td class="nro-nota"># Nota: {{ $note->nro_nota }}</td>
                </tr>
            </table>

            <!-- FILA 1: Nombre y Teléfonos -->
            <table class="data-table">
                <tr>
                    <td class="card" width="50%">
                        <span class="label">CLIENTE</span>
                        <div class="value">{{ $c?->name ?? '—' }}</div>
                    </td>
                    <td class="card" width="50%">
                        <span class="label">TELÉFONOS</span>
                        <div class="value">
                            @php
                                $phones = array_filter([$c?->phone, $c?->secondary_phone, $c?->third_phone]);
                            @endphp
                            {{ implode(' / ', $phones) ?: '—' }}
                        </div>
                    </td>
                </tr>
            </table>

            <!-- FILA 2: Dirección y Nro/Piso -->
            <table class="data-table">
                <tr>
                    <td class="card" width="70%">
                        <span class="label">DIRECCIÓN</span>
                        <div class="value">{{ $c?->primary_address ?? '—' }}</div>
                    </td>
                    <td class="card" width="30%">
                        <span class="label">NRO Y PISO</span>
                        <div class="value">{{ $c?->nro_piso ?? '—' }}</div>
                    </td>
                </tr>
            </table>

            <!-- FILA 3: CP, Ciudad, Ayuntamiento -->
            <table class="data-table">
                <tr>
                    <td class="card" width="25%">
                        <span class="label">CÓDIGO POSTAL</span>
                        <div class="value">{{ $cpCode ?? '—' }}</div>
                    </td>
                    <td class="card" width="35%">
                        <span class="label">CIUDAD</span>
                        <div class="value">{{ $city ?? '—' }}</div>
                    </td>
                    <td class="card" width="40%">
                        <span class="label">AYUNTAMIENTO</span>
                        <div class="value">{{ $note->ayuntamiento ?? '—' }}</div>
                    </td>
                </tr>
            </table>

            <!-- FILA 4: Fecha Visita, Horario, Teleoperadora, Comercial -->
            <table class="data-table">
                <tr>
                    <td class="card" width="20%">
                        <span class="label">FECHA VISITA</span>
                        <div class="value">{{ optional($note->assignment_date)->format('d/m/Y') ?? '—' }}</div>
                    </td>
                    <td class="card" width="20%">
                        <span class="label">HORARIO</span>
                        <div class="value">{{ $note->visit_schedule ?? '—' }}</div>
                    </td>
                    <td class="card" width="30%">
                        <span class="label">TELEOPERADORA</span>
                        <div class="value">{{ $note->user?->name ?? 'Sin asignar' }}</div>
                    </td>
                    <td class="card" width="30%">
                        <span class="label">COMERCIAL</span>
                        <div class="value">{{ $note->comercial?->name ?? 'Sin asignar' }}</div>
                    </td>
                </tr>
            </table>

            @php
                // Procesar Observaciones JSON
                $jsonRaw = $note->getAttribute('observations');
                if (is_string($jsonRaw)) $jsonRaw = json_decode($jsonRaw, true);
                $jsonRows = [];
                if (is_array($jsonRaw)) {
                    foreach ($jsonRaw as $row) {
                        $txt = is_array($row) ? ($row['observation'] ?? '') : $row;
                        if (trim($txt)) {
                            $author = '';
                            $date = '';
                            if (is_array($row)) {
                                if (!empty($row['author_id'])) {
                                    $u = \App\Models\User::find($row['author_id']);
                                    if ($u) $author = $u->name;
                                }
                                if (!empty($row['created_at'])) {
                                    $date = \Carbon\Carbon::parse($row['created_at'])->format('d/m/Y H:i');
                                }
                            }
                            $jsonRows[] = ['detalle' => $txt, 'autor' => $author, 'fecha' => $date];
                        }
                    }
                }

                // Observaciones Relación (Filtrar por rol para separar Comercial de Sala)
                $allObservations = $note->observations()->with('author.roles')->get();
                $anotaciones = $note->anotacionesVisitas()->with('autor.roles')->get();

                $comRows = $allObservations->filter(function($o) {
                    $user = $o->author;
                    if (!$user) return false;
                    return $user->hasRole('commercial') || $user->hasRole('team_leader');
                })->map(fn($o) => [
                    'detalle' => $o->observation,
                    'autor' => $o->author?->name ?? '—',
                    'fecha' => $o->created_at?->format('d/m/Y H:i') ?? ''
                ])->toArray();

                // Añadir Anotaciones de Visita (DENTRO, DE CAMINO, AUSENTE)
                $anotacionRows = $anotaciones->filter(function($a) {
                    return in_array($a->asunto, ['DENTRO', 'DE CAMINO', 'AUSENTE']);
                })->map(function($a) {
                    $user = $a->autor;
                    return [
                        'detalle' => $a->asunto,
                        'autor' => $user?->name ?? '—',
                        'fecha' => $a->created_at?->format('d/m/Y H:i') ?? ''
                    ];
                })->toArray();

                $comRows = array_merge($comRows, $anotacionRows);

                // Filtrar nulos o vacíos en comRows
                $comRows = array_filter($comRows, fn($r) => trim($r['detalle']));

                // Deduplicar Observaciones Comerciales
                $uniqueComRows = [];
                $seenCom = [];
                foreach ($comRows as $row) {
                    $key = trim($row['detalle']) . '|' . trim($row['autor']);
                    if (!isset($seenCom[$key])) {
                        $uniqueComRows[$key] = $row;
                        $seenCom[$key] = true;
                    } else {
                        if (!empty($row['fecha']) && empty($uniqueComRows[$key]['fecha'])) {
                            $uniqueComRows[$key] = $row;
                        }
                    }
                }
                $comRows = array_values($uniqueComRows);

                $jsonRowsFromTable = $allObservations->filter(function($o) {
                    $user = $o->author;
                    if (!$user) return true; // Por defecto a la primera sección si no hay autor
                    return $user->hasRole('teleoperator') || $user->hasRole('head_of_room') || $user->hasRole('admin') || $user->hasRole('super_admin');
                })->map(fn($o) => [
                    'detalle' => $o->observation,
                    'autor' => $o->author?->name ?? '—',
                    'fecha' => $o->created_at?->format('d/m/Y H:i') ?? ''
                ])->filter(fn($r) => trim($r['detalle']))->all();

                $jsonRows = array_merge($jsonRows, $jsonRowsFromTable);

                // Deduplicar Observaciones de Teleoperadora / Jefe de Sala
                // Se prioriza la versión que tiene fecha si el detalle y autor coinciden
                $uniqueJsonRows = [];
                $seen = [];
                foreach ($jsonRows as $row) {
                    $key = trim($row['detalle']) . '|' . trim($row['autor']);
                    if (!isset($seen[$key])) {
                        $uniqueJsonRows[$key] = $row;
                        $seen[$key] = true;
                    } else {
                        // Si ya existe pero el nuevo tiene fecha y el guardado no, lo reemplazamos
                        if (!empty($row['fecha']) && empty($uniqueJsonRows[$key]['fecha'])) {
                            $uniqueJsonRows[$key] = $row;
                        }
                    }
                }
                $jsonRows = array_values($uniqueJsonRows);

                // Observaciones Oficina
                $salaRows = $note->observacionesSala()->with('author')->get()->map(fn($o) => [
                    'detalle' => $o->observation,
                    'autor' => $o->author?->name ?? '—',
                    'fecha' => $o->created_at?->format('d/m/Y H:i') ?? ''
                ])->filter(fn($r) => trim($r['detalle']))->all();
            @endphp

            <div class="obs-title">OBSERVACIONES DE TELEOPERADORA / JEFE DE SALA</div>
            <table class="obs-table">
                <thead><tr><th width="20%">Autor</th><th width="60%">Detalle</th><th width="20%">Fecha</th></tr></thead>
                <tbody>
                    @forelse($jsonRows as $r)
                        <tr>
                            <td>{{ $r['autor'] }}</td>
                            <td>{{ $r['detalle'] }}</td>
                            <td style="font-size: 8px; color: #888;">{{ $r['fecha'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Sin observaciones</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="obs-title">OBSERVACIONES COMERCIAL</div>
            <table class="obs-table">
                <thead><tr><th width="20%">Autor</th><th width="60%">Detalle</th><th width="20%">Fecha</th></tr></thead>
                <tbody>
                    @forelse($comRows as $r)
                        <tr>
                            <td>{{ $r['autor'] }}</td>
                            <td>{{ $r['detalle'] }}</td>
                            <td style="font-size: 8px; color: #888;">{{ $r['fecha'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Sin observaciones</td></tr>
                    @endforelse
                </tbody>
            </table>

            @php
                $estado = $note->estado_terminal;
                $esSala = false;
                if ($estado instanceof \App\Enums\EstadoTerminal) {
                    $esSala = ($estado === \App\Enums\EstadoTerminal::SALA);
                } else {
                    $esSala = ((string)$estado === 'sala' || (string)$estado === \App\Enums\EstadoTerminal::SALA->value);
                }
            @endphp

            @if($esSala)
                <div class="obs-title">OBSERVACIONES DEL COMERCIAL (AL ENVIAR A OFICINA)</div>
                <table class="obs-table">
                    <thead><tr><th width="20%">Autor</th><th width="60%">Detalle</th><th width="20%">Fecha</th></tr></thead>
                    <tbody>
                        @forelse($salaRows as $r)
                            <tr>
                                <td>{{ $r['autor'] }}</td>
                                <td>{{ $r['detalle'] }}</td>
                                <td style="font-size: 8px; color: #888;">{{ $r['fecha'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Sin observaciones</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif

            <div class="footer">Generado: {{ date('d/m/Y H:i') }}</div>
        </div>
    @endforeach
</body>
</html>
