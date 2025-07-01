<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;

class NotasHoy extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas Hoy';
    protected static ?string $title = 'Notas Hoy';
    protected static ?string $slug = 'notas-hoy';
    protected static string $view = 'filament.commercial.pages.notas-hoy';
}
