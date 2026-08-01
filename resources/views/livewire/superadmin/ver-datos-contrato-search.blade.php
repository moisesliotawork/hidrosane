<div
    class="ver-datos-contrato-global"
    style="position: sticky; top: 0; z-index: 39; margin: 0.5rem 1rem 0;"
    wire:key="ver-datos-contrato-search"
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
        }
        html.dark .ver-datos-contrato-global__header {
            color: #dbeafe;
        }
        .ver-datos-contrato-global__body {
            padding: 0 0.85rem 0.75rem;
            border-top: 1px solid #bfdbfe;
        }
        html.dark .ver-datos-contrato-global__body {
            border-top-color: #3b82f6;
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
        .ver-datos-contrato-global__hint {
            margin: 0;
            padding-top: 0.35rem;
            font-size: 0.72rem;
            color: #1e40af;
        }
        html.dark .ver-datos-contrato-global__hint {
            color: #bfdbfe;
        }
    </style>

    <div class="ver-datos-contrato-global__box">
        <button type="button" class="ver-datos-contrato-global__header" wire:click="toggleOpen">
            <span>VER DATOS CONTRATO</span>
            <span>{{ $open ? '▾' : '▸' }}</span>
        </button>

        @if ($open)
            <div class="ver-datos-contrato-global__body">
                <p class="ver-datos-contrato-global__hint">
                    Persistente en todo SuperAdmin. Busca por nº admin y abre el formulario de Contratos.
                </p>
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
