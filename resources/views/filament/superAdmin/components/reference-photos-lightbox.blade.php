@php
    use Illuminate\Support\Facades\Storage;

    $photos = collect($photos ?? [])
        ->filter(fn ($path) => filled($path) && is_string($path))
        ->values()
        ->all();

    $resolveUrl = static function (string $path): ?string {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'blob:')) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        // Host del request actual (evita miniaturas rotas si APP_URL ≠ dominio real)
        $base = rtrim(request()->getSchemeAndHttpHost().request()->getBasePath(), '/');

        return $base.'/storage/'.$path;
    };

    $items = [];
    foreach ($photos as $path) {
        $url = $resolveUrl($path);
        if ($url === null) {
            continue;
        }
        $diskPath = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($diskPath, 'storage/')) {
            $diskPath = substr($diskPath, strlen('storage/'));
        }
        $exists = false;
        try {
            $exists = Storage::disk('public')->exists($diskPath);
        } catch (\Throwable) {
            $exists = false;
        }
        $items[] = [
            'path' => $path,
            'url' => $url,
            'exists' => $exists,
        ];
    }
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
    style="margin-bottom: 0.75rem;"
>
    @if ($items === [])
        <p style="margin: 0; font-size: 0.85rem; color: #6b7280;">
            Aún no hay fotos guardadas. Abre «Fotos de referencia», súbelas y pulsa «Guardar fotos».
        </p>
    @else
        <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; color: #374151;">
            {{ count($items) }} foto(s) — clic en una miniatura para ampliarla
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-start;">
            @foreach ($items as $i => $item)
                <button
                    type="button"
                    @click="show(@js($item['url']))"
                    title="Ampliar foto {{ $i + 1 }}"
                    style="
                        flex: 0 0 auto;
                        width: 7.5rem;
                        height: 7.5rem;
                        padding: 0;
                        margin: 0;
                        border: 2px solid {{ $item['exists'] ? '#d1d5db' : '#fca5a5' }};
                        border-radius: 0.5rem;
                        overflow: hidden;
                        background: #f3f4f6;
                        cursor: pointer;
                        position: relative;
                        box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
                    "
                >
                    <img
                        src="{{ $item['url'] }}"
                        alt="Referencia {{ $i + 1 }}"
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
                    ">Archivo no encontrado en disco</span>
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
