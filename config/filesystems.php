<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disco de documentos de cliente
    |--------------------------------------------------------------------------
    |
    | Disco donde viven los documentos subidos desde la app (DNIs, nóminas,
    | contratos firmados, precontractuales…). Se resuelve por config y no por
    | 'public' hardcodeado para poder cambiar de almacenamiento en un sitio.
    |
    | Valores: 'public'  → storage/app/public (local, histórico)
    |          'spaces'  → DigitalOcean Spaces, privado, URLs firmadas
    |
    | Mientras 'documents_fallback_disk' esté definido, si un documento no se
    | encuentra en el disco principal se busca en el de respaldo. Eso permite
    | apuntar a Spaces sin depender de que la migración esté al 100 %.
    |
    */

    'documents' => env('DOCUMENTS_DISK', 'public'),

    'documents_fallback' => env('DOCUMENTS_FALLBACK_DISK'),

    /*
    | Minutos de validez de las URLs firmadas de documentos.
    */

    'documents_url_ttl' => (int) env('DOCUMENTS_URL_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         * DigitalOcean Spaces (fra1). Bucket privado: los documentos NUNCA se
         * sirven por URL pública, sólo con temporaryUrl() firmada.
         *
         * 'throw' => true a propósito: en un disco remoto un fallo silencioso
         * significa perder el documento de un cliente sin enterarse.
         */
        'spaces' => [
            'driver' => 's3',
            'key' => env('DO_SPACES_KEY'),
            'secret' => env('DO_SPACES_SECRET'),
            'region' => env('DO_SPACES_REGION', 'fra1'),
            'bucket' => env('DO_SPACES_BUCKET'),
            'endpoint' => env('DO_SPACES_ENDPOINT', 'https://fra1.digitaloceanspaces.com'),
            'use_path_style_endpoint' => false,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
