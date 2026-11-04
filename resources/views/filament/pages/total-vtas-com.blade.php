<x-filament-panels::page>
    <div class="total-vtas-com">
        <p class="total-vtas-com__note">
            Ventas en solitario = comercial principal sin acompañante · sin contratos B · sin borradas · histórico completo.
        </p>

        <div class="total-vtas-com__scroll">
            <table class="total-vtas-com__table">
                <thead>
                    <tr>
                        <th>Alias</th>
                        <th>Id</th>
                        <th>Comercial</th>
                        <th class="is-num">Contratos</th>
                        <th class="is-num">Clientes</th>
                        <th>Primera</th>
                        <th>Última</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr class="{{ $fila['found'] ? '' : 'is-missing' }}">
                            <td class="is-alias">{{ $fila['alias'] }}</td>
                            <td class="is-id">{{ $fila['empleado_id'] }}</td>
                            <td class="is-name">{{ $fila['nombre'] }}</td>
                            <td class="is-num is-strong">{{ $fila['contratos'] }}</td>
                            <td class="is-num">{{ $fila['clientes'] }}</td>
                            <td>{{ $fila['primera'] ?? '—' }}</td>
                            <td>{{ $fila['ultima'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="is-empty">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="is-total-label">TOTAL</td>
                        <td class="is-num is-strong">{{ $totalContratos }}</td>
                        <td class="is-num is-strong">{{ $totalClientes }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <style>
        .total-vtas-com { display:flex; flex-direction:column; gap:12px; }
        .total-vtas-com__note {
            margin:0; font-size:12px; font-weight:600; color:#6b7280;
        }
        .total-vtas-com__scroll {
            overflow-x:auto; border:1px solid #d1d5db; border-radius:8px; background:#fff;
        }
        .total-vtas-com__table {
            width:100%; border-collapse:collapse; font-size:13px; color:#111827;
        }
        .total-vtas-com__table th {
            background:#e5e7eb; text-align:left; padding:10px 12px;
            font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase;
            border-bottom:1px solid #d1d5db; white-space:nowrap;
        }
        .total-vtas-com__table th.is-num,
        .total-vtas-com__table td.is-num { text-align:center; }
        .total-vtas-com__table td {
            padding:10px 12px; border-top:1px solid #e5e7eb; vertical-align:middle;
        }
        .total-vtas-com__table tbody tr:hover td { background:#f9fafb; }
        .total-vtas-com__table .is-alias { font-weight:800; color:#1e3a8a; }
        .total-vtas-com__table .is-id { font-weight:800; color:#a16207; }
        .total-vtas-com__table .is-name { font-weight:700; }
        .total-vtas-com__table .is-strong { font-weight:900; font-size:16px; color:#15803d; }
        .total-vtas-com__table tr.is-missing td { color:#9ca3af; font-style:italic; }
        .total-vtas-com__table tr.is-missing .is-strong { color:#9ca3af; }
        .total-vtas-com__table tfoot td {
            background:#f3f4f6; border-top:2px solid #9ca3af; font-weight:800;
        }
        .total-vtas-com__table .is-total-label { text-align:right; text-transform:uppercase; letter-spacing:.04em; }
        .total-vtas-com__table .is-empty { text-align:center; color:#6b7280; padding:20px; }
    </style>
</x-filament-panels::page>
