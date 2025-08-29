<?php

namespace App\Filament\Gerente\Resources\ComercialResource\Pages;

use App\Filament\Gerente\Resources\ComercialResource;
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
