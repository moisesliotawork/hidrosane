@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ContratoMesVariacionItem> $items */
    $items = $items ?? collect();
@endphp

@if ($items->isEmpty())
    <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
        {{ $emptyMessage ?? 'No hay contratos en esta sección.' }}
    </p>
@else
    <div style="overflow-x: auto;">
        <table class="contratos-mes-detalle-table">
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
                @foreach ($items as $item)
                    @php
                        $mesNum = null;
                        try {
                            $mesNum = (int) \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $item->mes_key)->month;
                        } catch (\Throwable) {
                            $mesNum = null;
                        }
                        $mesBadge = $mesNum
                            ? (\App\Support\Filament\MonthYearBadgeFilter::monthBadges()[$mesNum] ?? null)
                            : null;
                    @endphp
                    <tr>
                        <td>{{ $item->venta_id ?? '—' }}</td>
                        <td style="font-weight: 700;">{{ $item->quienLabel() }}</td>
                        <td>
                            <span class="contratos-mes-estado contratos-mes-estado--{{ $item->estado }}">
                                {{ $item->estadoLabel() }}
                            </span>
                        </td>
                        <td style="font-weight: 700;">{{ $item->nro_contr_adm ?? '—' }}</td>
                        <td style="font-weight: 700; color: #f97316; white-space: nowrap;">{{ $item->cliente_nombre ?? '—' }}</td>
                        <td style="font-weight: 700; white-space: nowrap;">{{ $item->dni ? implode(' ', str_split((string) $item->dni, 4)) : '—' }}</td>
                        <td>
                            @if ($mesBadge)
                                <span
                                    style="display:inline-flex;align-items:center;height:1.55rem;padding:0 0.55rem;border-radius:999px;font-size:0.68rem;letter-spacing:0.01em;white-space:nowrap;font-weight:700;background:{{ $mesBadge['bg'] }};color:{{ $mesBadge['text'] }};border:1px solid {{ $mesBadge['border'] }};"
                                >
                                    {{ $mesBadge['label'] }}
                                    {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $item->mes_key)->year }}
                                </span>
                            @else
                                {{ \App\Support\ContratosPorMesStats::labelForMonthKey((string) $item->mes_key) }}
                            @endif
                        </td>
                        <td style="text-transform: capitalize;">{{ $item->diaLabel() }}</td>
                        <td style="font-weight: 700;">{{ $item->fechaHoraLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
