<div class="mb-3">
    @if (filled($label ?? null))
        <div class="text-xl font-extrabold text-gray-950 dark:text-white">
            {{ mb_strtoupper($label) }}
        </div>
        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
            Este espacio está diseñado para que puedas actualizar y modificar el archivo de
            <strong>{{ $label }}</strong>. Es necesario actualizarlo para mantener tus datos al día.
        </p>
    @endif
</div>
