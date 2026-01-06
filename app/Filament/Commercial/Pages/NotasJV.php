<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;

class NotasJV extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas JV';
    protected static ?string $slug = 'notas-jv';
    protected static string $view = 'filament.commercial.pages.notas-jv';

    public function getTitle(): string
    {
        return 'Notas JV';
    }
}
