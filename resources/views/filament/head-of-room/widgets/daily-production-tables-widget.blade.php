<div style="padding:0 4px">
    @php
        $dp      = $this->data;
        $teleops = $dp['teleops'];
    @endphp

    {{-- ── TABLA 1: PRODUCCIÓN ── --}}
    <div style="margin-top:12px">
        <div style="font-size:13px;font-weight:700;color:#ffffff;margin-bottom:6px;letter-spacing:0.02em;padding:5px 12px;background:#475569;border-radius:6px;display:inline-block">
            Producción diaria
        </div>
        <div style="overflow-x:auto;border-radius:10px;border:2px solid #e2e8f0;background:#fff">
            <table style="border-collapse:collapse;font-size:11px;min-width:100%">
                <thead>
                    <tr style="background:#f1f5f9">
                        <th style="padding:5px 10px;text-align:left;font-weight:700;color:#475569;white-space:nowrap;position:sticky;left:0;background:#f1f5f9;z-index:1;min-width:110px;border-right:1px solid #e2e8f0">Teleop.</th>
                        <th style="padding:5px 6px;font-weight:700;color:#475569;white-space:nowrap;min-width:38px;text-align:center">Mes</th>
                        @for ($d = 1; $d <= 31; $d++)
                            <th style="padding:5px 3px;text-align:center;font-weight:700;color:#475569;min-width:22px">{{ $d }}</th>
                        @endfor
                        <th style="padding:5px 8px;text-align:center;font-weight:700;color:#475569;white-space:nowrap">Tot.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teleops as $i => $teleop)
                        @php
                            $uid      = $teleop->id;
                            $nombre   = $teleop->name . ' ' . mb_substr($teleop->last_name ?? '', 0, 1) . '.';
                            $currRow  = $dp['curr']->get($uid, collect());
                            $prevRow  = $dp['prev']->get($uid, collect());
                            $currTot  = $currRow->sum();
                            $prevTot  = $prevRow->sum();
                            $rowBorder = $i > 0 ? 'border-top:2px solid #e2e8f0' : '';
                        @endphp
                        <tr style="{{ $rowBorder }}">
                            <td rowspan="2" style="padding:4px 10px;font-weight:700;color:#1e293b;white-space:nowrap;position:sticky;left:0;background:#fff;z-index:1;border-right:1px solid #e2e8f0;vertical-align:middle">
                                <span style="font-size:10px;color:#be185d;font-weight:800;margin-right:3px">{{ $teleop->empleado_id }}</span>{{ $nombre }}
                            </td>
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#0c4a6e;background:#e0f2fe;text-align:center;white-space:nowrap">{{ $dp['curr_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $currRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;{{ $d > $dp['curr_days'] ? 'color:#e5e7eb' : ($val > 0 ? 'background:#dbeafe;font-weight:700;color:#1e40af' : 'color:#cbd5e1') }}">
                                    {{ $d > $dp['curr_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#1e40af;background:#eff6ff;white-space:nowrap">{{ $currTot ?: '—' }}</td>
                        </tr>
                        <tr style="border-top:1px solid #d1d5db;background:#e5e7eb">
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#374151;background:#d1d5db;text-align:center;white-space:nowrap">{{ $dp['prev_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $prevRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;background:#e5e7eb;{{ $d > $dp['prev_days'] ? 'color:#d1d5db' : ($val > 0 ? 'background:#c9cdd4;font-weight:700;color:#374151' : 'color:#9ca3af') }}">
                                    {{ $d > $dp['prev_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#374151;background:#c9cdd4;white-space:nowrap">{{ $prevTot ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── TABLA 2: VENTAS ── --}}
    <div style="margin-top:22px">
        <div style="font-size:13px;font-weight:700;color:#ffffff;margin-bottom:6px;letter-spacing:0.02em;padding:5px 12px;background:#15803d;border-radius:6px;display:inline-block">
            Ventas diarias
        </div>
        <div style="overflow-x:auto;border-radius:10px;border:2px solid #bbf7d0;background:#fff">
            <table style="border-collapse:collapse;font-size:11px;min-width:100%">
                <thead>
                    <tr style="background:#f0fdf4">
                        <th style="padding:5px 10px;text-align:left;font-weight:700;color:#166534;white-space:nowrap;position:sticky;left:0;background:#f0fdf4;z-index:1;min-width:110px;border-right:1px solid #bbf7d0">Teleop.</th>
                        <th style="padding:5px 6px;font-weight:700;color:#166534;white-space:nowrap;min-width:38px;text-align:center">Mes</th>
                        @for ($d = 1; $d <= 31; $d++)
                            <th style="padding:5px 3px;text-align:center;font-weight:700;color:#166534;min-width:22px">{{ $d }}</th>
                        @endfor
                        <th style="padding:5px 8px;text-align:center;font-weight:700;color:#166534;white-space:nowrap">Tot.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teleops as $i => $teleop)
                        @php
                            $uid      = $teleop->id;
                            $nombre   = $teleop->name . ' ' . mb_substr($teleop->last_name ?? '', 0, 1) . '.';
                            $currRow  = $dp['ventas_curr']->get($uid, collect());
                            $prevRow  = $dp['ventas_prev']->get($uid, collect());
                            $currTot  = $currRow->sum();
                            $prevTot  = $prevRow->sum();
                            $rowBorder = $i > 0 ? 'border-top:2px solid #bbf7d0' : '';
                        @endphp
                        <tr style="{{ $rowBorder }}">
                            <td rowspan="2" style="padding:4px 10px;font-weight:700;color:#1e293b;white-space:nowrap;position:sticky;left:0;background:#fff;z-index:1;border-right:1px solid #bbf7d0;vertical-align:middle">
                                <span style="font-size:10px;color:#be185d;font-weight:800;margin-right:3px">{{ $teleop->empleado_id }}</span>{{ $nombre }}
                            </td>
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#0c4a6e;background:#e0f2fe;text-align:center;white-space:nowrap">{{ $dp['curr_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $currRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;{{ $d > $dp['curr_days'] ? 'color:#e5e7eb' : ($val > 0 ? 'background:#dcfce7;font-weight:700;color:#166534' : 'color:#cbd5e1') }}">
                                    {{ $d > $dp['curr_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#166534;background:#dcfce7;white-space:nowrap">{{ $currTot ?: '—' }}</td>
                        </tr>
                        <tr style="border-top:1px solid #f0fdf4;background:#fafafa">
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#374151;background:#d1d5db;text-align:center;white-space:nowrap">{{ $dp['prev_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $prevRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;background:#e5e7eb;{{ $d > $dp['prev_days'] ? 'color:#d1d5db' : ($val > 0 ? 'background:#c9cdd4;font-weight:700;color:#374151' : 'color:#9ca3af') }}">
                                    {{ $d > $dp['prev_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#374151;background:#c9cdd4;white-space:nowrap">{{ $prevTot ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── TABLA 3: CONFIRMADAS ── --}}
    <div style="margin-top:22px">
        <div style="font-size:13px;font-weight:700;color:#ffffff;margin-bottom:6px;letter-spacing:0.02em;padding:5px 12px;background:#b45309;border-radius:6px;display:inline-block">
            Confirmadas diarias
        </div>
        <div style="overflow-x:auto;border-radius:10px;border:2px solid #fde68a;background:#fff">
            <table style="border-collapse:collapse;font-size:11px;min-width:100%">
                <thead>
                    <tr style="background:#fffbeb">
                        <th style="padding:5px 10px;text-align:left;font-weight:700;color:#78350f;white-space:nowrap;position:sticky;left:0;background:#fffbeb;z-index:1;min-width:110px;border-right:1px solid #fde68a">Teleop.</th>
                        <th style="padding:5px 6px;font-weight:700;color:#78350f;white-space:nowrap;min-width:38px;text-align:center">Mes</th>
                        @for ($d = 1; $d <= 31; $d++)
                            <th style="padding:5px 3px;text-align:center;font-weight:700;color:#78350f;min-width:22px">{{ $d }}</th>
                        @endfor
                        <th style="padding:5px 8px;text-align:center;font-weight:700;color:#78350f;white-space:nowrap">Tot.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teleops as $i => $teleop)
                        @php
                            $uid      = $teleop->id;
                            $nombre   = $teleop->name . ' ' . mb_substr($teleop->last_name ?? '', 0, 1) . '.';
                            $currRow  = $dp['conf_curr']->get($uid, collect());
                            $prevRow  = $dp['conf_prev']->get($uid, collect());
                            $currTot  = $currRow->sum();
                            $prevTot  = $prevRow->sum();
                            $rowBorder = $i > 0 ? 'border-top:2px solid #fde68a' : '';
                        @endphp
                        <tr style="{{ $rowBorder }}">
                            <td rowspan="2" style="padding:4px 10px;font-weight:700;color:#1e293b;white-space:nowrap;position:sticky;left:0;background:#fff;z-index:1;border-right:1px solid #fde68a;vertical-align:middle">
                                <span style="font-size:10px;color:#be185d;font-weight:800;margin-right:3px">{{ $teleop->empleado_id }}</span>{{ $nombre }}
                            </td>
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#0c4a6e;background:#e0f2fe;text-align:center;white-space:nowrap">{{ $dp['curr_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $currRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;{{ $d > $dp['curr_days'] ? 'color:#e5e7eb' : ($val > 0 ? 'background:#fef3c7;font-weight:700;color:#78350f' : 'color:#cbd5e1') }}">
                                    {{ $d > $dp['curr_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#78350f;background:#fef3c7;white-space:nowrap">{{ $currTot ?: '—' }}</td>
                        </tr>
                        <tr style="border-top:1px solid #fffbeb;background:#fafafa">
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#374151;background:#d1d5db;text-align:center;white-space:nowrap">{{ $dp['prev_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $prevRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;background:#e5e7eb;{{ $d > $dp['prev_days'] ? 'color:#d1d5db' : ($val > 0 ? 'background:#c9cdd4;font-weight:700;color:#374151' : 'color:#9ca3af') }}">
                                    {{ $d > $dp['prev_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#374151;background:#c9cdd4;white-space:nowrap">{{ $prevTot ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── TABLA 4: NULAS ── --}}
    <div style="margin-top:22px;margin-bottom:8px">
        <div style="font-size:13px;font-weight:700;color:#ffffff;margin-bottom:6px;letter-spacing:0.02em;padding:5px 12px;background:#991b1b;border-radius:6px;display:inline-block">
            Nulas diarias
        </div>
        <div style="overflow-x:auto;border-radius:10px;border:2px solid #fecaca;background:#fff">
            <table style="border-collapse:collapse;font-size:11px;min-width:100%">
                <thead>
                    <tr style="background:#fff1f2">
                        <th style="padding:5px 10px;text-align:left;font-weight:700;color:#991b1b;white-space:nowrap;position:sticky;left:0;background:#fff1f2;z-index:1;min-width:110px;border-right:1px solid #fecaca">Teleop.</th>
                        <th style="padding:5px 6px;font-weight:700;color:#991b1b;white-space:nowrap;min-width:38px;text-align:center">Mes</th>
                        @for ($d = 1; $d <= 31; $d++)
                            <th style="padding:5px 3px;text-align:center;font-weight:700;color:#991b1b;min-width:22px">{{ $d }}</th>
                        @endfor
                        <th style="padding:5px 8px;text-align:center;font-weight:700;color:#991b1b;white-space:nowrap">Tot.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teleops as $i => $teleop)
                        @php
                            $uid      = $teleop->id;
                            $nombre   = $teleop->name . ' ' . mb_substr($teleop->last_name ?? '', 0, 1) . '.';
                            $currRow  = $dp['nulas_curr']->get($uid, collect());
                            $prevRow  = $dp['nulas_prev']->get($uid, collect());
                            $currTot  = $currRow->sum();
                            $prevTot  = $prevRow->sum();
                            $rowBorder = $i > 0 ? 'border-top:2px solid #fecaca' : '';
                        @endphp
                        <tr style="{{ $rowBorder }}">
                            <td rowspan="2" style="padding:4px 10px;font-weight:700;color:#1e293b;white-space:nowrap;position:sticky;left:0;background:#fff;z-index:1;border-right:1px solid #fecaca;vertical-align:middle">
                                <span style="font-size:10px;color:#be185d;font-weight:800;margin-right:3px">{{ $teleop->empleado_id }}</span>{{ $nombre }}
                            </td>
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#0c4a6e;background:#e0f2fe;text-align:center;white-space:nowrap">{{ $dp['curr_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $currRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;{{ $d > $dp['curr_days'] ? 'color:#e5e7eb' : ($val > 0 ? 'background:#fee2e2;font-weight:700;color:#991b1b' : 'color:#cbd5e1') }}">
                                    {{ $d > $dp['curr_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#991b1b;background:#fee2e2;white-space:nowrap">{{ $currTot ?: '—' }}</td>
                        </tr>
                        <tr style="border-top:1px solid #fff1f2;background:#fafafa">
                            <td style="padding:4px 5px;font-size:10px;font-weight:700;color:#374151;background:#d1d5db;text-align:center;white-space:nowrap">{{ $dp['prev_label'] }}</td>
                            @for ($d = 1; $d <= 31; $d++)
                                @php $val = $prevRow->get($d, 0); @endphp
                                <td style="padding:3px 2px;text-align:center;background:#e5e7eb;{{ $d > $dp['prev_days'] ? 'color:#d1d5db' : ($val > 0 ? 'background:#c9cdd4;font-weight:700;color:#374151' : 'color:#9ca3af') }}">
                                    {{ $d > $dp['prev_days'] ? '' : ($val ?: '·') }}
                                </td>
                            @endfor
                            <td style="padding:3px 8px;text-align:center;font-weight:800;color:#374151;background:#c9cdd4;white-space:nowrap">{{ $prevTot ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
