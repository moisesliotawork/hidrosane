import preset from '../../../vendor/filament/filament/tailwind.config.preset'

/*
 * Tema compartido por los siete paneles.
 *
 * El CSS que Filament publica en public/css sólo trae las paletas `gray` y
 * `custom` (la primaria dinámica). Cualquier utilidad Tailwind suelta escrita
 * en un blade —bg-sky-600, text-green-600, border-amber-300…— se quedaba sin
 * regla detrás y no pintaba nada. Compilando este tema, Tailwind escanea el
 * código de la app y genera exactamente las utilidades que se usan.
 */
export default {
    presets: [preset],
    content: [
        './app/**/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './vendor/filament/**/*.blade.php',
    ],

    /*
     * Clases que el escáner no puede ver porque se arman en tiempo de
     * ejecución. Heredado del tailwind.config.js de la raíz.
     */
    safelist: [
        'bg-orange-100',
        'text-orange-800',
        'bg-green-100',
        'text-green-800',
        'bg-yellow-100',
        'text-yellow-800',
        'bg-gray-100',
        'text-gray-700',
    ],
}
