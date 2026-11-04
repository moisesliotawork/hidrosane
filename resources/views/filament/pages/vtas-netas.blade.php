<x-filament-panels::page>
    <div class="vtas-netas">
        <div class="vtas-netas__pills-row">
            <div class="vtas-netas__pills">
                @foreach ($this->anios as $anio)
                    <button
                        type="button"
                        wire:click="setAnio({{ $anio }})"
                        class="vtas-netas__pill {{ $anio === $this->anio ? 'is-active' : '' }}"
                    >
                        {{ $anio }}
                    </button>
                @endforeach
            </div>

            <button
                type="button"
                wire:click="mountAction('editarMesComercial')"
                class="vtas-netas__badge {{ $badgeDefinido ? 'is-defined' : 'is-undefined' }} {{ ! $badgeDefinido ? 'is-blink' : '' }} {{ $badgeUrgente ? 'is-urgent' : '' }}"
                title="Editar fechas del mes comercial"
            >
                {{ $badgeTexto }}
            </button>
        </div>

        <div class="vtas-netas__months">
            @foreach ($this->meses() as $mes)
                @php
                    $definido = in_array($mes['n'], $mesesDefinidos, true);
                    // Tachado si este mes es el anterior/posterior al seleccionado y no tiene fechas
                    $tachado = $this->mesVecinoSinDefinir($mes['n']);
                @endphp
                <button
                    type="button"
                    wire:click="setMes({{ $mes['n'] }})"
                    class="vtas-netas__month vtas-netas__month--{{ $mes['n'] }} {{ $mes['n'] === $this->mes ? 'is-active' : '' }} {{ $definido ? 'is-defined' : 'is-undefined' }} {{ $tachado ? 'is-struck' : '' }}"
                >
                    {{ $mes['label'] }}
                </button>
            @endforeach
        </div>

        <p class="vtas-netas__note">
            Brut. = Neut. + Entr. · Netas = entregados del mes comercial − nulos del mes comercial anterior.
            @unless ($badgeDefinido)
                · Este mes usa el calendario (sin rango comercial).
            @endunless
        </p>

        <div class="vtas-netas__scroll">
            <table class="vtas-netas__table">
                <thead>
                    <tr>
                        <th class="is-sticky col-com">Com/Rep</th>
                        <th class="is-center is-netas col-num">Netas</th>
                        <th class="is-center col-num">Brut.</th>
                        <th class="is-center col-num">Neut.</th>
                        <th class="is-center col-num">Entr.</th>
                        <th class="is-center col-num col-desc">Desc.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr>
                            <td class="is-sticky col-com">
                                @php
                                    $esAlba = (int) ($fila['empleado_id'] ?? 0) === 48;
                                    $generoClass = match (true) {
                                        (bool) ($fila['es_jefe'] ?? false) => 'is-jefe',
                                        $esAlba => 'is-unknown',
                                        ($fila['genero'] ?? null) === 'M' => 'is-male',
                                        ($fila['genero'] ?? null) === 'F' => 'is-female',
                                        default => 'is-unknown',
                                    };
                                @endphp
                                <span class="vtas-netas__comercial {{ $generoClass }}">
                                    <span class="vtas-netas__comercial-id">{{ $fila['empleado_id'] }}</span>
                                    <span class="vtas-netas__comercial-name">{{ $fila['nombre'] }}</span>
                                </span>
                            </td>
                            <td class="is-center is-netas col-num">{{ $fila['netas'] }}</td>
                            <td class="is-center col-num">{{ $fila['brut'] }}</td>
                            <td class="is-center col-num">{{ $fila['neut'] }}</td>
                            <td class="is-center col-num">{{ $fila['entr'] }}</td>
                            <td class="is-center col-num col-desc {{ $fila['desc'] > 0 ? 'is-desc' : '' }}">
                                {{ $fila['desc'] > 0 ? '-'.$fila['desc'] : '0' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="vtas-netas__empty">No hay comerciales activos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="vtas-netas__note is-center">
            Mostrando {{ $filas->count() }} comerciales
        </p>
    </div>

    <style>
        /* Menos hueco bajo el título "Ventas Netas" */
        .fi-header {
            margin-bottom: 0.35rem !important;
            padding-bottom: 0 !important;
        }
        .fi-page-content,
        .fi-main-ctn {
            row-gap: 0.35rem !important;
        }

        .vtas-netas {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 0;
        }
        .vtas-netas__pills-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .vtas-netas__pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .vtas-netas__pill {
            border: 0;
            border-radius: 6px;
            padding: 4px 10px;
            background: #e5e7eb;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
        }
        .vtas-netas__pill.is-active {
            background: #166534;
            color: #fff;
        }
        .vtas-netas__badge {
            border: 0;
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
            cursor: pointer;
            white-space: nowrap;
            line-height: 1.3;
        }
        .vtas-netas__badge.is-defined {
            background: #facc15;
            color: #713f12;
            padding: 7px 16px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .03em;
        }
        .vtas-netas__badge.is-undefined {
            background: #d1d5db;
            color: #4b5563;
        }
        .vtas-netas__badge.is-blink:not(.is-urgent) {
            animation: vtas-netas-blink-soft 1.4s ease-in-out infinite;
        }
        .vtas-netas__badge.is-urgent {
            animation: vtas-netas-blink-urgent 1s ease-in-out infinite;
        }
        .vtas-netas__badge:hover {
            filter: brightness(0.96);
        }
        @keyframes vtas-netas-blink-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
        @keyframes vtas-netas-blink-urgent {
            0%, 100% {
                background: #d1d5db;
                color: #4b5563;
            }
            50% {
                background: #ef4444;
                color: #fff;
            }
        }
        .vtas-netas__months {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
        }
        .vtas-netas__month {
            box-sizing: border-box;
            width: 100%;
            border: 1.5px solid #9ca3af;
            border-radius: 999px;
            padding: 6px 8px;
            background: #e5e7eb;
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            text-align: center;
            cursor: pointer;
        }
        /* Sin mes comercial definido: siempre gris */
        .vtas-netas__month.is-undefined {
            background: #e5e7eb !important;
            color: #6b7280 !important;
            border-color: #9ca3af !important;
        }
        .vtas-netas__month.is-undefined.is-active {
            background: #9ca3af !important;
            color: #fff !important;
            border-color: #6b7280 !important;
        }
        /* Mes anterior o posterior sin fechas: nombre tachado */
        .vtas-netas__month.is-struck {
            text-decoration: line-through;
            text-decoration-thickness: 2px;
        }
        /* Con mes comercial definido: color al activar */
        .vtas-netas__month.is-defined.is-active {
            color: #fff;
            border-color: transparent;
        }
        .vtas-netas__month--1.is-defined.is-active { background: #0284c7; border-color: #0284c7; }
        .vtas-netas__month--2.is-defined.is-active { background: #7c3aed; border-color: #7c3aed; }
        .vtas-netas__month--3.is-defined.is-active { background: #16a34a; border-color: #16a34a; }
        .vtas-netas__month--4.is-defined.is-active { background: #ca8a04; border-color: #ca8a04; }
        .vtas-netas__month--5.is-defined.is-active { background: #2563eb; border-color: #2563eb; }
        .vtas-netas__month--6.is-defined.is-active { background: #0d9488; border-color: #0d9488; }
        .vtas-netas__month--7.is-defined.is-active { background: #d97706; border-color: #d97706; }
        .vtas-netas__month--8.is-defined.is-active { background: #db2777; border-color: #db2777; }
        .vtas-netas__month--9.is-defined.is-active { background: #65a30d; border-color: #65a30d; }
        .vtas-netas__month--10.is-defined.is-active { background: #0891b2; border-color: #0891b2; }
        .vtas-netas__month--11.is-defined.is-active { background: #e11d48; border-color: #e11d48; }
        .vtas-netas__month--12.is-defined.is-active { background: #059669; border-color: #059669; }
        .vtas-netas__note {
            margin: 0;
            color: #374151;
            font-size: 12px;
            line-height: 1.4;
        }
        .vtas-netas__note.is-center {
            text-align: center;
        }
        .vtas-netas__scroll {
            overflow-x: visible;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            width: 100%;
        }
        .vtas-netas__table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            border-collapse: collapse;
            text-align: left;
            font-size: 12px;
            color: #111827;
            background: #fff;
        }
        .vtas-netas__table .col-com { width: 40%; }
        .vtas-netas__table .col-num { width: 12%; }
        .vtas-netas__table th {
            background: #14532d;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            padding: 6px 2px;
            white-space: nowrap;
        }
        .vtas-netas__table th.is-sticky {
            padding: 6px 6px;
        }
        .vtas-netas__table td {
            padding: 10px 2px;
            color: #111827;
            background: #fff;
            border-top: 1px solid #e5e7eb;
        }
        .vtas-netas__table td.is-sticky {
            padding: 8px 4px;
        }
        .vtas-netas__table .is-center {
            text-align: center;
        }
        .vtas-netas__table .is-sticky {
            position: sticky;
            left: 0;
            z-index: 1;
        }
        .vtas-netas__table th.is-sticky {
            z-index: 2;
            background: #14532d;
            color: #fff;
        }
        .vtas-netas__table th.is-netas {
            background: #93c5fd;
            color: #1e3a8a;
        }
        .vtas-netas__table td.is-sticky {
            background: #fff;
            overflow: hidden;
        }
        .vtas-netas__table tbody tr:hover td {
            background: #f3f4f6;
        }
        .vtas-netas__table tbody tr:hover td.is-netas {
            background: #f3f4f6;
        }
        .vtas-netas__comercial {
            display: inline-flex;
            align-items: center;
            gap: 0.35em;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            border-radius: 6px;
            padding: 7px 6px;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
        }
        .vtas-netas__comercial.is-male {
            background: #2563eb;
        }
        .vtas-netas__comercial.is-jefe {
            background: #bfdbfe;
            color: #1e3a8a;
        }
        .vtas-netas__comercial.is-female {
            background: #db2777;
        }
        .vtas-netas__comercial.is-unknown {
            background: transparent;
            color: #111827;
            padding-left: 2px;
            padding-right: 2px;
        }
        .vtas-netas__comercial-id {
            flex: 0 0 auto;
            font-size: 16px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.02em;
        }
        .vtas-netas__comercial-name {
            flex: 1 1 auto;
            min-width: 0;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.15;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .vtas-netas__table td.col-num {
            font-size: 15px;
            font-weight: 800;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .vtas-netas__table td.is-netas {
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: 800;
        }
        .vtas-netas__table td.is-desc {
            color: #be123c;
            font-weight: 800;
        }
        .vtas-netas__empty {
            padding: 24px 12px;
            text-align: center;
            color: #374151;
        }

        @media (max-width: 640px) {
            .fi-header {
                margin-bottom: 0.15rem !important;
            }
            .vtas-netas {
                gap: 6px;
            }
            .vtas-netas__pills-row {
                flex-direction: column;
                align-items: stretch;
            }
            .vtas-netas__badge {
                text-align: center;
                white-space: normal;
            }
            .vtas-netas__months {
                gap: 5px;
            }
            .vtas-netas__month {
                padding: 5px 4px;
                font-size: 11px;
            }
            .vtas-netas__note {
                font-size: 11px;
            }
            .vtas-netas__table .col-com { width: 40%; }
            .vtas-netas__table .col-num { width: 12%; }
            .vtas-netas__table th {
                font-size: 9px;
                letter-spacing: 0;
                padding: 6px 1px;
            }
            .vtas-netas__table th.is-sticky {
                padding: 6px 4px;
            }
            .vtas-netas__table td {
                padding: 9px 1px;
            }
            .vtas-netas__table td.is-sticky {
                padding: 7px 3px;
            }
            .vtas-netas__comercial {
                padding: 6px 4px;
                gap: 0.3em;
                border-radius: 5px;
            }
            .vtas-netas__comercial-id {
                font-size: 15px;
            }
            .vtas-netas__comercial-name {
                font-size: 14px;
            }
            .vtas-netas__table td.col-num {
                font-size: 14px;
            }
        }
    </style>
</x-filament-panels::page>
