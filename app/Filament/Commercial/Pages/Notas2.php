<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;

class Notas2 extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'NOTAS';
    protected static ?int $navigationSort = -1;
    protected static ?string $slug = 'notas';
    protected static string $view = 'filament.commercial.pages.notas2';

    public static function getNavigationLabel(): string
    {
        return 'NOTAS';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['team_leader', 'sales_manager'])) {
            return 'Notas JE';
        }

        return 'NOTAS';
    }

}
