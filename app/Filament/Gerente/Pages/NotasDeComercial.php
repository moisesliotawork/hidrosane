<?php

namespace App\Filament\Gerente\Pages;

use Filament\Pages\Page;

class NotasDeComercial extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = null; // no mostrar en menú
    protected static ?string $title = 'Notas del Comercial';
    protected static ?string $slug = 'notas-de-comercial';
    protected static string $view = 'filament.gerente.pages.notas-de-comercial';
    public ?int $comercialId = null;
    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->comercialId = (int) request()->query('comercial_id');
        abort_unless($this->comercialId > 0, 404);
    }

}
