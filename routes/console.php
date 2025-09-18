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
    ->dailyAt('00:01')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/supervisiones_purge.log'));

Schedule::command('notes:sala-overdue')
    ->dailyAt('23:30')
    ->timezone('Europe/Madrid') // ajusta si prefieres otra zona
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/notes_sala_overdue.log'));
