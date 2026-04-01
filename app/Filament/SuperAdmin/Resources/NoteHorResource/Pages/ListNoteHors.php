<?php

namespace App\Filament\SuperAdmin\Resources\NoteHorResource\Pages;

use App\Filament\SuperAdmin\Resources\NoteHorResource;
use Filament\Resources\Pages\ListRecords;

class ListNoteHors extends ListRecords
{
    protected static string $resource = NoteHorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
