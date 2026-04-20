<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('picking:backfill')
    ->everyFiveMinutes()
    ->timezone('Europe/Madrid')   // 🔥 IMPORTANTE
    ->between('08:00', '21:55')   // 🔥 SOLO en ese rango
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/picking.log'));

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

Schedule::command('creams:generate-next-day')
    ->dailyAt('22:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/creams_generate_next_day.log'));

Schedule::command('bi:build-teleoperator-monthly-stats --year=' . now()->year)
    ->everyTenMinutes()
    ->timezone('Europe/Madrid')   // 🔥 IMPORTANTE
    ->between('08:00', '21:55')   // 🔥 SOLO en ese rango
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/teleop_monthly_stats.log'));


