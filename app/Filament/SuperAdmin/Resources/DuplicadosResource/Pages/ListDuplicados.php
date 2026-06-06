<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Pages;

use App\Filament\SuperAdmin\Resources\DuplicadosResource;
use Filament\Resources\Pages\ListRecords;

class ListDuplicados extends ListRecords
{
    protected static string $resource = DuplicadosResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
