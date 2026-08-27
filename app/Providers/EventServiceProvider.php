<?php

namespace App\Providers;

use App\Events\VentaCreada;
use App\Listeners\EnviarVentaATelegram;
use App\Events\NotaConfirmada;
use App\Listeners\EnviarNotaConfirmadaATelegram;
use App\Events\NotaNula;
use App\Listeners\EnviarNotaNulaATelegram;
use App\Events\NotaEnviadaAOficina;
use App\Listeners\EnviarNotaOficinaATelegram;
use App\Events\NotasEnviadasAOficinaBulk;
use App\Listeners\EnviarNotasOficinaBulkATelegram;
use App\Listeners\EnviarNotasOficinaBulkPorEmail;
use App\Events\PuntoComercialEnviado;
use App\Listeners\EnviarPuntoComercialATelegram;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        VentaCreada::class => [
            EnviarVentaATelegram::class,
        ],

        NotaConfirmada::class => [
            EnviarNotaConfirmadaATelegram::class,
        ],

        NotaNula::class => [
            EnviarNotaNulaATelegram::class,
        ],

        NotaEnviadaAOficina::class => [
            EnviarNotaOficinaATelegram::class,
        ],

        NotasEnviadasAOficinaBulk::class => [
            EnviarNotasOficinaBulkATelegram::class,
            EnviarNotasOficinaBulkPorEmail::class,
        ],

        PuntoComercialEnviado::class => [
            EnviarPuntoComercialATelegram::class,
        ],
    ];
}
