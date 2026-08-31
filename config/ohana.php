<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vuelta a Ohana desde los paneles de Hidrosane
    |--------------------------------------------------------------------------
    |
    | Espejo de config/sso.php: allí se recibe, aquí se emite. Comparten
    | SSO_SECRET, y el token lleva un campo 'aud' para que uno emitido hacia
    | Ohana no valga en el /sso/entrar de esta misma aplicación.
    |
    | Los perfiles (clave -> rol exigido aquí) salen de config/demo.php, que es
    | la única lista de perfiles del proyecto.
    |
    */

    'url' => env('OHANA_URL', 'https://appohana.com'),

];
