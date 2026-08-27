<?php

namespace App\Filament\SuperAdmin\Resources\ClientesBorradosResource\Pages;

use App\Filament\SuperAdmin\Resources\ClientesBorradosResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListClientesBorrados extends ListRecords
{
    protected static string $resource = ClientesBorradosResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
