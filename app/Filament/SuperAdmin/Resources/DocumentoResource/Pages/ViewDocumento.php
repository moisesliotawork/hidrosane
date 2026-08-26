<?php

namespace App\Filament\SuperAdmin\Resources\DocumentoResource\Pages;

use App\Filament\SuperAdmin\Resources\DocumentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumento extends ViewRecord
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar'),
            Actions\DeleteAction::make()
                ->label('Borrar'),
        ];
    }
}
