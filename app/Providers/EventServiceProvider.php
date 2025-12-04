<?php

namespace App\Providers;

use App\Events\VentaCreada;
use App\Listeners\EnviarVentaATelegram;
use App\Events\NotaConfirmada;
use App\Listeners\EnviarNotaConfirmadaATelegram;
use App\Events\NotaNula;
use App\Listeners\EnviarNotaNulaATelegram;
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
    ];
}
