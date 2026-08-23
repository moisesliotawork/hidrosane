<?php

namespace App\Filament\Commercial\Pages;

use App\Models\AppSetting;
use App\Support\Storage\DocumentStorage;
use Filament\Pages\Page;

class PoliticasViewer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Ofertas Comerciales en Rigor';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Ofertas Comerciales en Rigor';

    protected static ?string $slug = 'politicas';

    protected static string $view = 'filament.commercial.pages.politicas-viewer';

    /** URL del PDF (firmada si vive en almacenamiento remoto) o null si no existe */
    public function getPdfUrl(): ?string
    {
        $path = data_get(AppSetting::get('politicas_comisiones_pdf'), 'path');

        return $path ? DocumentStorage::url($path) : null;
    }
}
