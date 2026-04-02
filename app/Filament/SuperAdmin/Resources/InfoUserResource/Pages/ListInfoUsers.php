<?php

namespace App\Filament\SuperAdmin\Resources\InfoUserResource\Pages;

use App\Filament\SuperAdmin\Resources\InfoUserResource;
use Filament\Resources\Pages\ListRecords;

class ListInfoUsers extends ListRecords
{
    protected static string $resource = InfoUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
