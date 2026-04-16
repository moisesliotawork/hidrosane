<x-filament-panels::page>

<div style="padding:0 4px">

    {{-- Tabs periodo --}}
    <div style="display:flex;gap:8px;margin-bottom:16px">
        <button wire:click="setPeriodo('this')"
            style="padding:7px 20px;border-radius:6px;font-size:15px;font-weight:700;border:none;cursor:pointer;{{ $periodo === 'this' ? 'background:#0284c7;color:#fff' : 'background:#e5e7eb;color:#374151' }}">
            Mes actual
        </button>
        <button wire:click="setPeriodo('prev')"
            style="padding:7px 20px;border-radius:6px;font-size:15px;font-weight:700;border:none;cursor:pointer;{{ $periodo === 'prev' ? 'background:#0284c7;color:#fff' : 'background:#e5e7eb;color:#374151' }}">
            Mes anterior
        </button>
    </div>

    {{-- Acordeones por teleoperadora --}}
    <div style="display:flex;flex-direction:column;gap:10px">
        @foreach($this->teleoperadoras as $teleop)
            @php
                $notas = $teleop->notes;
                $conf  = (int)($teleop->confirmadas_count ?? 0);
                $vta   = (int)($teleop->vendidas_count ?? 0);
                $prod  = (int)($teleop->aproduccion_count ?? 0);
                $pct   = $prod > 0 ? round(($conf + $vta) / $prod * 100, 1) : 0;

                $pctBg    = $pct === 0.0 ? '#f3f4f6'  : ($pct >= 70 ? '#dcfce7'  : ($pct >= 40 ? '#fef9c3' : '#fee2e2'));
                $pctColor = $pct === 0.0 ? '#9ca3af'  : ($pct >= 70 ? '#14532d'  : ($pct >= 40 ? '#78350f' : '#991b1b'));
            @endphp

            <div x-data="{ open: false }" style="border-radius:10px;border:2px solid #e2e8f0;overflow:hidden;background:#ffffff">

                {{-- Cabecera clicable --}}
                <div @click="open = !open"
                    style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8fafc;cursor:pointer;user-select:none;border-bottom:1px solid #e2e8f0">

                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        {{-- ID --}}
                        <span style="font-size:14px;font-weight:800;padding:3px 10px;border-radius:9999px;background:#be185d;color:#fff">
                            {{ $teleop->empleado_id }}
                        </span>
                        {{-- Nombre --}}
                        <span style="font-size:16px;font-weight:700;color:#1e293b">
                            {{ $teleop->name }} {{ $teleop->last_name }}
                        </span>

                        {{-- Badges stats --}}
                        <span style="font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;background:{{ $conf > 0 ? '#fef3c7' : '#f1f5f9' }};color:{{ $conf > 0 ? '#78350f' : '#94a3b8' }}">
                            CONF: {{ $conf }}
                        </span>
                        <span style="font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;background:{{ $vta > 0 ? '#dcfce7' : '#f1f5f9' }};color:{{ $vta > 0 ? '#14532d' : '#94a3b8' }}">
                            VTA: {{ $vta }}
                        </span>
                        <span style="font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;background:#e0f2fe;color:#0c4a6e">
                            PROD: {{ $prod }}
                        </span>
                        <span style="font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;background:{{ $pctBg }};color:{{ $pctColor }}">
                            {{ $pct }}%
                        </span>

                        @if($notas->isNotEmpty())
                            <span style="font-size:13px;color:#64748b">
                                · {{ $notas->count() }} nota{{ $notas->count() !== 1 ? 's' : '' }}
                            </span>
                        @endif
                    </div>

                    <span x-text="open ? '▲' : '▼'" style="font-size:13px;color:#64748b;margin-left:8px;flex-shrink:0"></span>
                </div>

                {{-- Tabla de notas --}}
                <div x-show="open" x-cloak style="overflow-x:auto;background:#ffffff">
                    @if($notas->isEmpty())
                        <p style="text-align:center;padding:16px;font-size:14px;color:#94a3b8;font-style:italic">
                            Sin notas de venta o confirmación este período
                        </p>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:14px">
                            <thead>
                                <tr style="background:#f1f5f9">
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;white-space:nowrap;font-size:13px">Nº Nota</th>
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;font-size:13px">Cliente</th>
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;white-space:nowrap;font-size:13px">F. Creación</th>
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;white-space:nowrap;font-size:13px">Nº Cliente</th>
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;font-size:13px">Teléfonos</th>
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;white-space:nowrap;font-size:13px">F. Declaración</th>
                                    <th style="padding:9px 14px;text-align:left;font-weight:700;color:#475569;font-size:13px">Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($notas as $nota)
                                    @php
                                        $nroNota = strlen($nota->nro_nota) === 5
                                            ? substr($nota->nro_nota, 0, 3) . ' ' . substr($nota->nro_nota, 3, 2)
                                            : $nota->nro_nota;

                                        $fmt = fn(?string $p): string => $p
                                            ? implode(' ', str_split(preg_replace('/\D/', '', $p), 3))
                                            : '';

                                        $phones = collect([
                                            $nota->customer?->phone,
                                            $nota->customer?->secondary_phone,
                                            $nota->customer?->third_phone,
                                        ])->filter()->map($fmt)->join(' / ');

                                        $nroCliente = $nota->venta?->nro_cliente_adm ?? $nota->customer?->nro_cliente ?? '';

                                        $esVenta = $nota->estado_terminal?->value === 'venta';
                                        $rowBg   = $esVenta ? '#f0fdf4' : '#fffbeb';
                                    @endphp
                                    <tr style="border-top:1px solid #e2e8f0;background:{{ $rowBg }}">
                                        <td style="padding:9px 14px;font-family:monospace;font-weight:800;color:#9d174d;white-space:nowrap;font-size:14px">
                                            {{ $nroNota }}
                                        </td>
                                        <td style="padding:9px 14px;font-weight:700;color:#1e293b;font-size:14px">
                                            {{ strtoupper(trim(($nota->customer?->first_names ?? '') . ' ' . ($nota->customer?->last_names ?? ''))) ?: '—' }}
                                        </td>
                                        <td style="padding:9px 14px;color:#475569;white-space:nowrap;font-size:14px">
                                            {{ $nota->created_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td style="padding:9px 14px;color:#475569;white-space:nowrap;font-size:14px">
                                            {{ $nroCliente ?: '—' }}
                                        </td>
                                        <td style="padding:9px 14px;color:#475569;white-space:nowrap;font-size:14px">
                                            {{ $phones ?: '—' }}
                                        </td>
                                        <td style="padding:9px 14px;white-space:nowrap;font-weight:700;font-size:14px;color:{{ $esVenta ? '#15803d' : '#b45309' }}">
                                            {{ $nota->fecha_declaracion?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td style="padding:9px 14px">
                                            @if($esVenta)
                                                <span style="font-size:13px;font-weight:700;padding:3px 9px;border-radius:4px;background:#16a34a;color:#fff">VENTA</span>
                                            @else
                                                <span style="font-size:13px;font-weight:700;padding:3px 9px;border-radius:4px;background:#d97706;color:#fff">CONF</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

            </div>
        @endforeach
    </div>

</div>
</x-filament-panels::page>
