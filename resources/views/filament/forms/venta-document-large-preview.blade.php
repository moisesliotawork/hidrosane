<div class="mb-3">
    @if (filled($label ?? null))
        <div class="text-xl font-extrabold text-gray-950 dark:text-white">
            {{ mb_strtoupper($label) }}
        </div>
        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
            Este espacio está diseñado para que puedas actualizar y modificar el archivo de
            <strong>{{ $label }}</strong>. Es necesario actualizarlo para mantener tus datos al día.
        </p>
        <p class="mt-1 text-xs font-semibold leading-5 text-amber-700 dark:text-amber-300">
            Usa JPG, HEIC o PNG. No subas fotos RAW / ProRAW (.dng): el iPhone las marca como
            «subida completa» pero luego no se pueden guardar.
        </p>
    @endif
</div>
