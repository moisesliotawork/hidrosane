<?php

namespace App\Filament\Teleoperator\Resources\NoteResource\Pages;

use App\Filament\Teleoperator\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}
