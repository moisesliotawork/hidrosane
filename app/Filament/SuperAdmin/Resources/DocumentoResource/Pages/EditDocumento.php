<?php

namespace App\Filament\SuperAdmin\Resources\DocumentoResource\Pages;

use App\Filament\SuperAdmin\Resources\DocumentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumento extends EditRecord
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->label('Borrar'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_array($data['file_path'] ?? null)) {
            $data['file_path'] = reset($data['file_path']) ?: null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
