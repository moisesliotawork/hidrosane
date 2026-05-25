<?php

namespace App\Filament\SuperAdmin\Resources\Com1Com2Resource\Pages;

use App\Filament\SuperAdmin\Resources\Com1Com2Resource;
use Filament\Resources\Pages\ListRecords;

class ListCom1Com2 extends ListRecords
{
    protected static string $resource = Com1Com2Resource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
