<?php

namespace App\Filament\SuperAdmin\Resources\DocumentoResource\Pages;

use App\Filament\SuperAdmin\Resources\DocumentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentos extends ListRecords
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Subir documento'),
        ];
    }
}
