<?php

namespace App\Filament\Repartidor\Pages;

use Filament\Pages\Page;

class RepartosHoy extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Repartos Hoy';
    protected static ?string $title = 'Repartos Hoy';
    protected static ?string $slug = 'repartos-hoy';
    protected static string $view = 'filament.repartidor.pages.repartos-hoy';
}
