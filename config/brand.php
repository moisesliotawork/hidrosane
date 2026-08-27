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
    | (#0284c7). Cubre el chrome de los siete paneles.
    |
    | Las utilidades Tailwind sueltas que se escriben en los blades
    | (bg-sky-600, text-green-600…) las genera el tema compilado de
    | resources/css/filament/, no esta variable: son literales en el código y
    | no siguen a BRAND_COLOR. Si cambias de color de marca, repásalas a mano.
    */
    'color' => env('BRAND_COLOR', 'Lime'),

];
