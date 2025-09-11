<?php

namespace App\Filament\Gerente\Resources\SupervisionResource\Pages;

use App\Filament\Gerente\Resources\SupervisionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupervision extends EditRecord
{
    protected static string $resource = SupervisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
