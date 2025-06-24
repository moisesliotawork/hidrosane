<?php

namespace App\Filament\HeadOfRoom\Resources\NoteResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
}
