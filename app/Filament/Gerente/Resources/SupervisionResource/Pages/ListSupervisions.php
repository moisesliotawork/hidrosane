<?php

namespace App\Filament\Gerente\Resources\SupervisionResource\Pages;

use App\Filament\Gerente\Resources\SupervisionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupervisions extends ListRecords
{
    protected static string $resource = SupervisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
