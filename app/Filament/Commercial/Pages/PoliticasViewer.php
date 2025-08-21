<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;
use App\Models\AppSetting;

class PoliticasViewer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Políticas y Comisiones';
    protected static ?string $title = 'Políticas y Comisiones';
    protected static ?string $slug = 'politicas';
    protected static string $view = 'filament.commercial.pages.politicas-viewer';

    /** URL pública del PDF o null si no existe */
    public function getPdfUrl(): ?string
    {
        $path = data_get(AppSetting::get('politicas_comisiones_pdf'), 'path');
        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }
}
