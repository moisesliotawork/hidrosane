<?php

namespace App\Filament\SuperAdmin\Resources\NotasBorradasResource\Pages;

use App\Filament\SuperAdmin\Resources\NotasBorradasResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListNotasBorradas extends ListRecords
{
    protected static string $resource = NotasBorradasResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
