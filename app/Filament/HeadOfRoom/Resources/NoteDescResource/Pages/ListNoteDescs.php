<?php

namespace App\Filament\HeadOfRoom\Resources\NoteDescResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteDescResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\EstadoTerminal;
use App\Models\Note;

class ListNoteDescs extends ListRecords
{
    protected static string $resource = NoteDescResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
