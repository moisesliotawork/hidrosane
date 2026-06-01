<?php

namespace App\Filament\SuperAdmin\Resources\TlfChangeResource\Pages;

use App\Filament\SuperAdmin\Resources\TlfChangeResource;
use Filament\Resources\Pages\ListRecords;

class ListTlfChanges extends ListRecords
{
    protected static string $resource = TlfChangeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
