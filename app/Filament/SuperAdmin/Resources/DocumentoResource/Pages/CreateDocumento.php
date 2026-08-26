<?php

namespace App\Filament\SuperAdmin\Resources\DocumentoResource\Pages;

use App\Filament\SuperAdmin\Resources\DocumentoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDocumento extends CreateRecord
{
    protected static string $resource = DocumentoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = Auth::id();

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
