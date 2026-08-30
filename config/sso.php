<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entrada desde otra aplicación (Ohana → Hidrosane)
    |--------------------------------------------------------------------------
    |
    | Ohana firma un token corto y de un solo uso; aquí se valida y se entra al
    | panel correspondiente sin escribir credenciales.
    |
    | SIN SECRETO LA RUTA NO EXISTE (404). El mismo valor tiene que estar en el
    | .env de las dos aplicaciones, y no debe ser el APP_KEY: si un día hay que
    | rotarlo, no queremos invalidar de paso las sesiones ni lo que haya
    | cifrado en la base de datos.
    |
    | Generar con:  php -r "echo bin2hex(random_bytes(32));"
    |
    */

    'secret' => env('SSO_SECRET'),

    // Segundos de validez del token. Es un salto de una pestaña a otra:
    // más de un minuto solo amplía la ventana en que un token robado sirve.
    'ttl' => (int) env('SSO_TTL', 60),

];
