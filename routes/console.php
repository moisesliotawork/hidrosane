<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('picking:backfill')
    ->everyFiveMinutes()
    ->withoutOverlapping()      // evita ejecuciones solapadas
    ->onOneServer()             // si usas varios servers
    ->appendOutputTo(storage_path('logs/picking.log')); // (opcional) log

Schedule::command('supervisiones:purge-expired')
    ->dailyAt('00:00')                // todos los días a medianoche
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/supervisiones_purge.log'));
