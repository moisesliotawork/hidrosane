<?php

namespace App\Filament\Admin\Resources\ComercialResource\Pages;

use App\Filament\Admin\Resources\ComercialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComercials extends ListRecords
{
    protected static string $resource = ComercialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
