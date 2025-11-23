<?php

namespace App\Filament\Admin\Resources\CreamDailyControlResource\Pages;

use App\Filament\Admin\Resources\CreamDailyControlResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreamDailyControls extends ListRecords
{
    protected static string $resource = CreamDailyControlResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
