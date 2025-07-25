<?php

namespace App\Filament\Repartidor\Pages;

use Filament\Pages\Page;

class MisRepartos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Mis Repartos';
    protected static ?string $title = 'Mis Repartos';
    protected static ?string $slug = 'repartos';
    protected static string $view = 'filament.repartidor.pages.repartos';
}
