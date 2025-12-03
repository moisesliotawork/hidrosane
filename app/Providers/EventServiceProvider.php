<?php

namespace App\Providers;

use App\Events\VentaCreada;
use App\Listeners\EnviarVentaATelegram;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        VentaCreada::class => [
            EnviarVentaATelegram::class,
        ],
    ];
}
