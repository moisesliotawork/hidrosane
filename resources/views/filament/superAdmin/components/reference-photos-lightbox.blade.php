@php
    use Illuminate\Support\Facades\Storage;

    $photos = collect($photos ?? [])
        ->filter(fn ($path) => filled($path) && is_string($path))
        ->values();
@endphp

<div
    x-data="{
        open: false,
        src: null,
        show(url) { this.src = url; this.open = true },
        close() { this.open = false; this.src = null },
    }"
    @keydown.escape.window="close()"
    class="space-y-2"
>
    @if ($photos->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aún no hay fotos guardadas. Súbelas abajo y pulsa «Guardar fotos».
        </p>
    @else
        <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">
            Clic en una foto para verla a pantalla grande
        </p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($photos as $path)
                @php
                    $url = Storage::disk('public')->url($path);
                @endphp
                <button
                    type="button"
                    @click="show(@js($url))"
                    class="group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-left shadow-sm transition hover:border-amber-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                    title="Ver en grande"
                >
                    <img
                        src="{{ $url }}"
                        alt="Referencia"
                        class="h-36 w-full object-cover transition group-hover:scale-[1.02]"
                        loading="lazy"
                    />
                    <span class="absolute inset-x-0 bottom-0 bg-black/55 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                        Ampliar
                    </span>
                </button>
            @endforeach
        </div>
    @endif

    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 p-3 sm:p-6"
        style="display: none;"
        @click.self="close()"
    >
        <button
            type="button"
            @click="close()"
            class="absolute right-4 top-4 rounded-full bg-white/90 px-3 py-1 text-sm font-bold text-gray-900 shadow hover:bg-white"
        >
            Cerrar ✕
        </button>
        <img
            x-show="src"
            :src="src"
            alt="Referencia ampliada"
            class="max-h-[92vh] max-w-[96vw] rounded-lg object-contain shadow-2xl"
            @click.stop
        />
    </div>
</div>
