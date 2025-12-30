<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;

class Notas2 extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas 2';
    protected static ?string $title = 'Notas Nuevas';
    protected static ?string $slug = 'notas';
    protected static string $view = 'filament.commercial.pages.notas2';
}
