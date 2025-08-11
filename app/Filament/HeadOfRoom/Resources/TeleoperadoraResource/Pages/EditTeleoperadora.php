<?php

namespace App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages;

use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource;
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
