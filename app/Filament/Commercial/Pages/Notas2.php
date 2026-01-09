<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;

class Notas2 extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas Nuevas 2026';
    protected static ?string $slug = 'notas';
    protected static string $view = 'filament.commercial.pages.notas2';

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['team_leader', 'sales_manager'])) {
            return 'Notas JE';
        }

        return 'Notas';
    }
}
