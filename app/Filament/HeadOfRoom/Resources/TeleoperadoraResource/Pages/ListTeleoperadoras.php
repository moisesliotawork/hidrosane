<?php

namespace App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages;

use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeleoperadoras extends ListRecords
{
    protected static string $resource = TeleoperadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
