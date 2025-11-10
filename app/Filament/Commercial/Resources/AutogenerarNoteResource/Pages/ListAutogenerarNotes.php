<?php

namespace App\Filament\Commercial\Resources\AutogenerarNoteResource\Pages;

use App\Filament\Commercial\Resources\AutogenerarNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutogenerarNotes extends ListRecords
{
    protected static string $resource = AutogenerarNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
