<?php

namespace App\Filament\Admin\Resources\VentasViejasResource\Pages;

use App\Filament\Admin\Resources\VentasViejasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVentasViejas extends ListRecords
{
    protected static string $resource = VentasViejasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
