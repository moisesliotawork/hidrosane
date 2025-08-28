<?php

namespace App\Filament\Gerente\Resources\TeleoperadoraResource\Pages;

use App\Filament\Gerente\Resources\TeleoperadoraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeleoperadora extends EditRecord
{
    protected static string $resource = TeleoperadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
