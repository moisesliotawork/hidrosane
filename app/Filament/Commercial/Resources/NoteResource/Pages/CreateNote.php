<?php

namespace App\Filament\Commercial\Resources\NoteResource\Pages;

use App\Filament\Commercial\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
