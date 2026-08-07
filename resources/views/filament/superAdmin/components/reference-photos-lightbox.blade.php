@php
    use Illuminate\Support\Facades\Storage;

    $photos = collect($photos ?? [])
        ->filter(fn ($path) => filled($path) && is_string($path))
        ->values()
        ->all();

    $urls = [];
    foreach ($photos as $path) {
        try {
            $urls[] = Storage::disk('public')->url($path);
        } catch (\Throwable) {
            // ignore broken paths
        }
    }
@endphp

<div
    x-data="{
        open: false,
        src: null,
        show(url) {
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
    style="margin-bottom: 0.75rem;"
>
    @if ($urls === [])
        <p style="margin: 0; font-size: 0.85rem; color: #6b7280;">
            Aún no hay fotos guardadas. Súbelas abajo y pulsa «Guardar fotos».
        </p>
    @else
        <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; color: #374151;">
            {{ count($urls) }} foto(s) — clic en una miniatura para ampliarla
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-start;">
            @foreach ($urls as $i => $url)
                <button
                    type="button"
                    @click="show(@js($url))"
                    title="Ampliar foto {{ $i + 1 }}"
                    style="
                        flex: 0 0 auto;
                        width: 7.5rem;
                        height: 7.5rem;
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
                        alt="Referencia {{ $i + 1 }}"
                        loading="lazy"
                        style="
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                            pointer-events: none;
                        "
                    />
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
            x-transition.opacity.duration.150ms
            @click.self="close()"
            style="
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: none;
                align-items: center;
                justify-content: center;
                background: rgb(0 0 0 / 0.88);
                padding: 1rem;
            "
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
                alt="Referencia ampliada"
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
