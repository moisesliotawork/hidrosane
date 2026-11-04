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

    /*
    | Los tres primeros son el destino del botón Ohana → Hidrosane.
    | empleado_id admite el número tal como se ve en Ohana y variantes
    | con o sin ceros, por si la ficha se guardó de otra forma.
    | Alba no salta de app: opera en Hidrosane como comercial 004.
    */
    'perfiles' => [
        'admin' => [
            'rol' => 'admin',
            'panel' => 'admin',
            'etiqueta' => 'Admin',
            'email' => 'abbyvj@gmail.com',
            'empleado_id' => ['002', '02', '2'],
        ],
        'superadmin' => [
            'rol' => 'app_support',
            'panel' => 'superAdmin',
            'etiqueta' => 'Superadmin',
            'email' => 'soporte@ohana.es',
            'empleado_id' => ['00', '000', '0'],
        ],
        'gerente' => [
            'rol' => 'gerente_general',
            'panel' => 'gerente',
            'etiqueta' => 'Gerente',
            'email' => 'direccion@ohanadistribucion.com',
            'empleado_id' => ['001', '01', '1'],
        ],
    ],

    'operativos' => [
        [
            'empleado_id' => '00',
            'email' => 'soporte@ohana.es',
            'name' => 'Rafael Perez',
            'rol' => 'app_support',
        ],
        [
            'empleado_id' => '001',
            'email' => 'direccion@ohanadistribucion.com',
            'name' => 'Jonna',
            'rol' => 'gerente_general',
        ],
        [
            'empleado_id' => '002',
            'email' => 'abbyvj@gmail.com',
            'name' => 'Abby',
            'rol' => 'admin',
        ],
        [
            'empleado_id' => '004',
            'email' => 'albaohanavigo@gmail.com',
            'emails_anteriores' => ['albiiux1505@gmail.com'],
            'name' => 'Alba Alvarez',
            'rol' => 'commercial',
            'clave' => '1079',
        ],
    ],

];
