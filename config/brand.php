<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identidad de marca
    |--------------------------------------------------------------------------
    |
    | Los valores por defecto son los de Ohana. Cada instalación (Hidrosane,
    | etc.) los sobreescribe por .env, de modo que el código pueda seguir
    | sincronizándose con Ohana sin arrastrar un rebranding a mano.
    |
    | NO cubre el contenido legal del contrato (razón social, CIF, direcciones
    | y cláusulas de protección de datos en resources/views/pdf.blade.php):
    | eso es contenido propio de cada empresa, no branding, y vive como
    | delta del repo a propósito.
    |
    */

    // Nombre visible: logo del panel, <title> de las páginas sueltas y del PDF.
    'name' => env('BRAND_NAME', env('APP_NAME', 'Ohana Plus')),

    // Ruta del logo relativa a public/.
    'logo' => env('BRAND_LOGO', 'images/logo.png'),

    /*
    | Color primario de Filament. Acepta el nombre de una paleta de
    | Filament\Support\Colors\Color (Lime, Sky, Blue, Emerald…) o un hex
    | (#0284c7). Cubre todo el chrome de los siete paneles.
    |
    | Cubre todo lo que realmente se pinta. Las clases Tailwind sueltas de
    | acento que hay en algunos blades (bg-sky-*, bg-lime-*, bg-orange-*…)
    | NO tienen CSS detrás: la app no compila Tailwind propio y el CSS
    | precompilado de Filament solo trae las paletas gray y primary. Son
    | inertes hoy, tanto aquí como en Ohana. Si algún día se añade un tema
    | propio de Filament (viteTheme), empezarían a pintar y habría que
    | repasarlas.
    */
    'color' => env('BRAND_COLOR', 'Lime'),

];
