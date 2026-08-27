@php
    $items = collect($photos ?? [])
        ->filter(fn ($url) => filled($url) && is_string($url))
        ->values()
        ->all();
@endphp

<div
    x-data="{
        open: false,
        src: null,
        show(url) {
            if (!url) return;
            this.src = url;
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            this.src = null;
            document.body.style.overflow = '';
        }
    }"
    @keydown.escape.window="if (open) close()"
    style="margin-bottom: 0.25rem;"
>
    @if ($items === [])
        <p style="margin: 0; font-size: 0.85rem; color: #6b7280;">
            No hay documentos originales guardados para este registro.
        </p>
    @else
        <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; color: #374151;">
            {{ count($items) }} documento(s) — clic en una miniatura para ampliarla
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-start;">
            @foreach ($items as $i => $url)
                <button
                    type="button"
                    @click="show(@js($url))"
                    title="Ampliar documento {{ $i + 1 }}"
                    style="
                        flex: 0 0 auto;
                        width: 9rem;
                        height: 9rem;
                        padding: 0;
                        margin: 0;
                        border: 2px solid #d1d5db;
                        border-radius: 0.5rem;
                        overflow: hidden;
                        background: #f3f4f6;
                        cursor: pointer;
                        position: relative;
                        box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
                    "
                >
                    <img
                        src="{{ $url }}"
                        alt="Documento {{ $i + 1 }}"
                        loading="lazy"
                        style="
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                            pointer-events: none;
                        "
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    />
                    <span style="
                        display: none;
                        position: absolute;
                        inset: 0;
                        align-items: center;
                        justify-content: center;
                        padding: 0.4rem;
                        font-size: 0.65rem;
                        font-weight: 700;
                        color: #991b1b;
                        background: #fef2f2;
                        text-align: center;
                    ">No se pudo cargar</span>
                    <span style="
                        position: absolute;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgb(0 0 0 / 0.65);
                        color: #fff;
                        font-size: 0.62rem;
                        font-weight: 800;
                        letter-spacing: 0.04em;
                        text-transform: uppercase;
                        text-align: center;
                        padding: 0.2rem 0.15rem;
                    ">{{ $i + 1 }} · Ampliar</span>
                </button>
            @endforeach
        </div>
    @endif

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            @click.self="close()"
            x-bind:style="open
                ? 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgb(0 0 0 / 0.88);padding:1rem;'
                : 'display:none;'"
        >
            <button
                type="button"
                @click="close()"
                style="
                    position: absolute;
                    top: 1rem;
                    right: 1rem;
                    z-index: 100000;
                    border: 0;
                    border-radius: 999px;
                    background: #fff;
                    color: #111827;
                    font-size: 0.85rem;
                    font-weight: 800;
                    padding: 0.45rem 0.9rem;
                    cursor: pointer;
                    box-shadow: 0 4px 12px rgb(0 0 0 / 0.25);
                "
            >
                Cerrar ✕
            </button>

            <img
                x-show="src"
                :src="src"
                alt="Documento ampliado"
                @click.stop
                style="
                    max-width: min(96vw, 1200px);
                    max-height: 90vh;
                    width: auto;
                    height: auto;
                    object-fit: contain;
                    border-radius: 0.5rem;
                    box-shadow: 0 10px 40px rgb(0 0 0 / 0.45);
                    background: #111;
                "
            />
        </div>
    </template>
</div>
