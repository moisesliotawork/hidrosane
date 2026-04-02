<?php

namespace App\Filament\SuperAdmin\Resources\NoteHorResource\Pages;

use App\Filament\SuperAdmin\Resources\NoteHorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNoteHor extends EditRecord
{
    protected static string $resource = NoteHorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
