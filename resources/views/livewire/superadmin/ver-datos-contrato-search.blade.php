<div
    class="ver-datos-contrato-global {{ ($hasBorradosAlert || $hasFechaCambiosAlert || $hasIdentidadAlert) ? 'ver-datos-contrato-global--alert' : '' }}"
    style="position: sticky; top: 0; z-index: 39; margin: 0.5rem 1rem 0;"
    wire:key="ver-datos-contrato-search"
    wire:poll.30s="refreshBorradosCount"
>
    <style>
        .ver-datos-contrato-global__box {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 0.65rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
            overflow: hidden;
        }
        html.dark .ver-datos-contrato-global__box {
            background: #1e3a8a;
            border-color: #3b82f6;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__box {
            border: 2px solid #dc2626;
            background: #fef2f2;
            animation: ver-datos-contrato-blink 1.6s ease-in-out infinite;
        }
        html.dark .ver-datos-contrato-global--alert .ver-datos-contrato-global__box {
            background: #450a0a;
            border-color: #f87171;
        }
        @keyframes ver-datos-contrato-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.72; }
        }
        .ver-datos-contrato-global__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.55rem 0.85rem;
            border: 0;
            background: transparent;
            cursor: pointer;
            font-weight: 800;
            font-size: 0.8rem;
            letter-spacing: 0.03em;
            color: #1e3a8a;
            text-align: left;
            gap: 0.75rem;
        }
        html.dark .ver-datos-contrato-global__header {
            color: #dbeafe;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__header {
            color: #991b1b;
        }
        html.dark .ver-datos-contrato-global--alert .ver-datos-contrato-global__header {
            color: #fecaca;
        }
        .ver-datos-contrato-global__title {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .ver-datos-contrato-global__badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.35rem;
            height: 1.2rem;
            padding: 0 0.4rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 900;
            line-height: 1;
            background: #dc2626;
            color: #fff;
        }
        .ver-datos-contrato-global__body {
            padding: 0 0.85rem 0.75rem;
            border-top: 1px solid #bfdbfe;
        }
        html.dark .ver-datos-contrato-global__body {
            border-top-color: #3b82f6;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__body {
            border-top-color: #fca5a5;
        }
        html.dark .ver-datos-contrato-global--alert .ver-datos-contrato-global__body {
            border-top-color: #f87171;
        }
        .ver-datos-contrato-global__form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding-top: 0.65rem;
        }
        .ver-datos-contrato-global__form input {
            flex: 1 1 14rem;
            min-width: 10rem;
            height: 2.1rem;
            padding: 0 0.65rem;
            border: 1px solid #93c5fd;
            border-radius: 0.4rem;
            font-size: 0.85rem;
            font-weight: 700;
            background: #fff;
            color: #111827;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__form input {
            border-color: #fca5a5;
        }
        .ver-datos-contrato-global__form button[type="submit"] {
            height: 2.1rem;
            padding: 0 0.85rem;
            border: 0;
            border-radius: 0.4rem;
            background: #1d4ed8;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }
        .ver-datos-contrato-global__form button[type="submit"]:hover {
            background: #1e40af;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__form button[type="submit"] {
            background: #dc2626;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__form button[type="submit"]:hover {
            background: #b91c1c;
        }
        .ver-datos-contrato-global__hint {
            margin: 0;
            padding-top: 0.35rem;
            font-size: 0.72rem;
            color: #1e40af;
        }
        html.dark .ver-datos-contrato-global__hint {
            color: #bfdbfe;
        }
        .ver-datos-contrato-global--alert .ver-datos-contrato-global__hint {
            color: #991b1b;
            font-weight: 700;
        }
        html.dark .ver-datos-contrato-global--alert .ver-datos-contrato-global__hint {
            color: #fecaca;
        }
        .ver-datos-contrato-global__borrados-link,
        .ver-datos-contrato-global__fecha-btn {
            display: inline-flex;
            align-items: center;
            margin-top: 0.45rem;
            margin-right: 0.35rem;
            padding: 0.3rem 0.65rem;
            border-radius: 0.35rem;
            background: #dc2626;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
            border: 0;
            cursor: pointer;
        }
        .ver-datos-contrato-global__borrados-link:hover,
        .ver-datos-contrato-global__fecha-btn:hover {
            background: #b91c1c;
            color: #fff;
        }
        .ver-datos-contrato-global__fecha-btn--ghost {
            background: #fff;
            color: #991b1b;
            border: 1px solid #dc2626;
        }
        .ver-datos-contrato-global__fecha-btn--ghost:hover {
            background: #fee2e2;
            color: #991b1b;
        }
        .ver-datos-contrato-global__fecha-block {
            margin-top: 0.65rem;
            padding-top: 0.55rem;
            border-top: 1px dashed #fca5a5;
        }
        .ver-datos-contrato-global__fecha-list {
            margin: 0.4rem 0 0;
            padding: 0;
            list-style: none;
            max-height: 11rem;
            overflow: auto;
        }
        .ver-datos-contrato-global__fecha-list li {
            font-size: 0.72rem;
            font-weight: 600;
            color: #7f1d1d;
            padding: 0.28rem 0;
            border-bottom: 1px solid #fecaca;
            line-height: 1.35;
        }
        html.dark .ver-datos-contrato-global__fecha-list li {
            color: #fecaca;
            border-bottom-color: #7f1d1d;
        }
        .ver-datos-contrato-global__fecha-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.35rem;
        }
    </style>

    <div class="ver-datos-contrato-global__box">
        <button type="button" class="ver-datos-contrato-global__header" wire:click="toggleOpen">
            <span class="ver-datos-contrato-global__title">
                <span>VER DATOS CONTRATO</span>
                @if ($hasBorradosAlert)
                    <span class="ver-datos-contrato-global__badge" title="Contratos en papelera">
                        {{ $borradosCount }}
                    </span>
                    <span>¡BORRADO!</span>
                @endif
                @if ($hasFechaCambiosAlert)
                    <span class="ver-datos-contrato-global__badge" title="Cambios de fecha">
                        {{ $fechaCambiosCount }}
                    </span>
                    <span>¡FECHA CAMBIADA!</span>
                @endif
                @if ($hasIdentidadAlert)
                    <span class="ver-datos-contrato-global__badge" title="Nombre o DNI que coincide con otro cliente">
                        {{ $identidadCount }}
                    </span>
                    <span>¡COINCIDENCIA!</span>
                @endif
            </span>
            <span>{{ $open ? '▾' : '▸' }}</span>
        </button>

        @if ($open)
            <div class="ver-datos-contrato-global__body">
                @if ($hasBorradosAlert)
                    <p class="ver-datos-contrato-global__hint">
                        Hay {{ $borradosCount === 1 ? '1 contrato borrado' : "{$borradosCount} contratos borrados" }}
                        pendiente{{ $borradosCount === 1 ? '' : 's' }} de revisar (Restaurar / RESERVA).
                    </p>
                    <a href="{{ $borradosUrl }}" class="ver-datos-contrato-global__borrados-link">
                        Ir a Contratos borrados
                    </a>
                    <button
                        type="button"
                        class="ver-datos-contrato-global__fecha-btn ver-datos-contrato-global__fecha-btn--ghost"
                        wire:click="descartarAlertaBorrados"
                        wire:confirm="¿Descartar la alerta? Los contratos siguen guardados en Contratos borrados."
                        wire:loading.attr="disabled"
                    >
                        DESCARTAR ALERTA
                    </button>
                @elseif (! $hasFechaCambiosAlert && ! $hasIdentidadAlert)
                    <p class="ver-datos-contrato-global__hint">
                        Persistente en todo SuperAdmin. Busca por nº admin y abre el formulario de Contratos.
                    </p>
                @endif

                @if ($hasFechaCambiosAlert)
                    <div class="ver-datos-contrato-global__fecha-block">
                        <p class="ver-datos-contrato-global__hint" style="padding-top: 0;">
                            Cambios de <strong>fecha de contrato</strong> pendientes
                            ({{ $fechaCambiosCount }}):
                        </p>
                        <ul class="ver-datos-contrato-global__fecha-list">
                            @foreach ($fechaCambios as $cambio)
                                <li>
                                    Nº <strong>{{ $cambio->nro_contr_adm ?: '—' }}</strong>
                                    · {{ $cambio->cliente_nombre ?: '—' }}
                                    · {{ $cambio->fechaAnteriorLabel() }} → {{ $cambio->fechaNuevaLabel() }}
                                    · {{ $cambio->quienLabel() }}
                                </li>
                            @endforeach
                        </ul>
                        <div class="ver-datos-contrato-global__fecha-actions">
                            <button
                                type="button"
                                class="ver-datos-contrato-global__fecha-btn"
                                wire:click="descargarCambiosFecha"
                                wire:loading.attr="disabled"
                            >
                                Descargar cambios de fecha
                            </button>
                            <button
                                type="button"
                                class="ver-datos-contrato-global__fecha-btn ver-datos-contrato-global__fecha-btn--ghost"
                                wire:click="descartarCambiosFecha"
                                wire:confirm="¿Marcar todos los cambios de fecha como revisados? El alerta dejará de parpadear."
                                wire:loading.attr="disabled"
                            >
                                Descartar alerta de fechas
                            </button>
                        </div>
                    </div>
                @endif

                @if ($hasIdentidadAlert)
                    <div class="ver-datos-contrato-global__fecha-block">
                        <p class="ver-datos-contrato-global__hint" style="padding-top: 0;">
                            Admin cambió <strong>nombre y/o DNI</strong> y coincide con otro cliente
                            ({{ $identidadCount }}). Revisa si hay que evitar una fusión errónea de contratos.
                        </p>
                        <ul class="ver-datos-contrato-global__fecha-list">
                            @foreach ($identidadAlertas as $alerta)
                                <li>
                                    Nº <strong>{{ $alerta->nro_contr_adm ?: '—' }}</strong>
                                    · Usuario: {{ $alerta->quienLabel() }}
                                    · Antes: {{ $alerta->nombre_antes ?: '—' }} / DNI {{ $alerta->dni_antes ?: '—' }}
                                    · Después: {{ $alerta->nombre_despues ?: '—' }} / DNI {{ $alerta->dni_despues ?: '—' }}
                                    · Coincide por {{ $alerta->viaLabel() }} con: {{ $alerta->coincidencia_nombre ?: '—' }} / DNI {{ $alerta->coincidencia_dni ?: '—' }}
                                    · {{ $alerta->cuandoLabel() }}
                                </li>
                            @endforeach
                        </ul>
                        <div class="ver-datos-contrato-global__fecha-actions">
                            <button
                                type="button"
                                class="ver-datos-contrato-global__fecha-btn ver-datos-contrato-global__fecha-btn--ghost"
                                wire:click="descartarAlertaIdentidad"
                                wire:confirm="¿Descartar la alerta de coincidencia? Los contratos no se fusionan y los borrados siguen en Contratos borrados."
                                wire:loading.attr="disabled"
                            >
                                DESCARTAR ALERTA
                            </button>
                        </div>
                    </div>
                @endif

                <form class="ver-datos-contrato-global__form" wire:submit.prevent="buscar">
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="nro"
                        placeholder="Nº contrato admin"
                        autocomplete="off"
                    >
                    <button type="submit" wire:loading.attr="disabled">Buscar</button>
                </form>
            </div>
        @endif
    </div>
</div>
