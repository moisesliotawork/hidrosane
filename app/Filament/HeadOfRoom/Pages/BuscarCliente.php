<?php

namespace App\Filament\HeadOfRoom\Pages;

use Filament\Pages\Page;

class BuscarCliente extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Buscar cliente';
    protected static ?string $title = 'Buscar cliente';
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.head-of-room.pages.buscar-cliente';
}
