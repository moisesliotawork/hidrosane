@props([
    'filas',
    'mostrarAnulacion' => false,
    'variante' => 'normal',
])

<div class="sabana-com__scroll">
    <table class="sabana-com__table {{ $variante === 'descontar' ? 'is-descontar' : '' }}">
        <thead>
            <tr>
                <th class="col-num">#</th>
                <th>CONTRATO</th>
                <th class="col-cliente">CLIENTE</th>
                <th>F. ENTR</th>
                @if ($mostrarAnulacion)
                    <th>F. ANUL</th>
                @endif
                <th>C1</th>
                <th>C2</th>
                <th>R1</th>
                <th>R2</th>
                <th>ESTADO</th>
                <th>Imp. Com.</th>
                <th>Imp. Rep.</th>
                <th>Vta.Com.</th>
                <th>Vta Com. Ac.</th>
                <th>Vta esp.</th>
                <th>Edad&gt;80</th>
                <th>Pagando</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $i => $fila)
                <tr>
                    <td class="col-num">{{ $i + 1 }}</td>
                    <td><span class="sabana-com__contrato">{{ $fila['contrato'] }}</span></td>
                    <td class="col-cliente is-left is-cliente">
                        <div>{{ $fila['cliente'] }}</div>
                        <div class="sabana-com__cliente-badges">
                            <span class="sabana-com__fuente {{ $fila['fuente_clase'] }}">{{ $fila['fuente_badge'] }}</span>
                            @if ($fila['es_nuevo_cliente'] ?? false)
                                <span class="sabana-com__fuente is-nuevo">¡NUEVO CLIENTE!</span>
                            @endif
                            @if ($fila['cliente_perdido'] ?? false)
                                <span class="sabana-com__fuente is-perdido">¡CLIENTE PERDIDO!</span>
                            @endif
                        </div>
                    </td>
                    <td><span class="sabana-com__fecha-entr">{{ $fila['fecha_entrega'] }}</span></td>
                    @if ($mostrarAnulacion)
                        <td>
                            @if (! empty($fila['fecha_anulacion']))
                                <span class="sabana-com__fecha-anul">{{ $fila['fecha_anulacion'] }}</span>
                            @endif
                        </td>
                    @endif
                    <td class="is-ids {{ $fila['highlight'] === 'c1' ? 'is-hl' : '' }}">{{ $fila['c1'] }}</td>
                    <td class="is-ids {{ $fila['highlight'] === 'c2' ? 'is-hl' : '' }}">{{ $fila['c2'] }}</td>
                    <td class="is-ids {{ $fila['highlight'] === 'r1' ? 'is-hl' : '' }}">{{ $fila['r1'] }}</td>
                    <td class="is-ids {{ $fila['highlight'] === 'r2' ? 'is-hl' : '' }}">{{ $fila['r2'] }}</td>
                    <td class="is-estado {{ ($fila['es_neutra'] ?? false) ? 'is-neutra' : '' }}">
                        {{ mb_strtoupper($fila['estado']) }}
                    </td>
                    <td class="is-money">{{ $fila['imp_com'] }}</td>
                    <td class="is-money-muted">{{ $fila['imp_rep'] }}</td>
                    <td class="is-vta">
                        {{ $fila['vta_com'] }}
                        @if ($fila['vta_com_pct'])
                            <span class="sabana-com__pct">(50%)</span>
                        @endif
                    </td>
                    <td class="is-vta">
                        {{ $fila['vta_com_ac'] }}
                        @if ($fila['vta_com_ac_pct'])
                            <span class="sabana-com__pct">(50%)</span>
                        @endif
                    </td>
                    <td class="is-vta">{{ $fila['vta_esp'] !== '' ? $fila['vta_esp'] : '0' }}</td>
                    <td class="is-edad is-left">
                        <div class="sabana-com__edad-flag">{{ $fila['edad_gt_80'] }}</div>
                        <div class="sabana-com__edad-detail">e: {{ $fila['edad_texto'] }}</div>
                        <div class="sabana-com__edad-detail">/c: {{ $fila['cuotas'] }}</div>
                    </td>
                    <td class="is-pagando">{{ $fila['pagando'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $mostrarAnulacion ? 17 : 16 }}" class="sabana-com__empty">
                        @if ($variante === 'descontar')
                            Sin ventas a descontar en este periodo.
                        @elseif ($mostrarAnulacion)
                            Sin ventas neutras en este periodo.
                        @else
                            Sin ventas entregadas.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
