<?php

namespace App\Filament\HeadOfRoom\Resources\NoteDescResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteDescResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNoteDesc extends EditRecord
{
    protected static string $resource = NoteDescResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
