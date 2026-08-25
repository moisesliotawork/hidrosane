<div class="mb-4">
    @if (filled($label ?? null))
        <div class="text-xl font-extrabold text-gray-950 dark:text-white">
            {{ mb_strtoupper($label) }}
        </div>
        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
            Este espacio está diseñado para que puedas actualizar y modificar el archivo de
            <strong>{{ $label }}</strong>. Es necesario actualizarlo para mantener tus datos al día.
        </p>
    @endif

    @if (filled($url ?? null))
        <a href="{{ $url }}" target="_blank" rel="noopener" class="mt-3 block">
            <img
                src="{{ $url }}"
                alt=""
                class="h-auto w-full rounded-lg border border-gray-200 dark:border-gray-700"
            />
        </a>
    @endif
</div>
