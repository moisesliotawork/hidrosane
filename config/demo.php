<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Acceso directo de demostración
    |--------------------------------------------------------------------------
    |
    | Con DEMO_LOGIN=true la pantalla de login muestra botones que entran a los
    | paneles sin escribir credenciales. Pensado SOLO para la instancia de
    | demostración: cualquiera que llegue a la URL entra con esos privilegios.
    |
    | Apagado por defecto. Antes de que la instalación reciba datos reales hay
    | que dejarlo apagado y repasar los usuarios de SpecialUsersSeeder.
    |
    */

    'login' => (bool) env('DEMO_LOGIN', false),

    'perfiles' => [
        'admin' => [
            'rol' => 'admin',
            'panel' => 'admin',
            'etiqueta' => 'Admin',
        ],
        'superadmin' => [
            'rol' => 'app_support',
            'panel' => 'superAdmin',
            'etiqueta' => 'Superadmin',
        ],
        'gerente' => [
            'rol' => 'gerente_general',
            'panel' => 'gerente',
            'etiqueta' => 'Gerente',
        ],
    ],

];
