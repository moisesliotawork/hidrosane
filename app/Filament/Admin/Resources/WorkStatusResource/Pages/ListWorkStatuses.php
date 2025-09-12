<?php

namespace App\Filament\Admin\Resources\WorkStatusResource\Pages;

use App\Filament\Admin\Resources\WorkStatusResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkStatuses extends ListRecords
{
    protected static string $resource = WorkStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
