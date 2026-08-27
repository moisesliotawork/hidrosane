<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Destinatarios de notificaciones internas
    |--------------------------------------------------------------------------
    |
    | Sin valor no se envía nada. El destino NO tiene fallback a propósito:
    | un default cableado haría que una instalación nueva enviase datos de sus
    | clientes al buzón de otra empresa.
    |
    */

    // Buzón de oficina que recibe el PDF de notas enviadas en bloque.
    'oficina' => env('NOTIFICACIONES_OFICINA_EMAIL'),

];
