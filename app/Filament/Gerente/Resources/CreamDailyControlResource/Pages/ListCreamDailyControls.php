<?php

namespace App\Filament\Gerente\Resources\CreamDailyControlResource\Pages;

use App\Filament\Gerente\Resources\CreamDailyControlResource;
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
