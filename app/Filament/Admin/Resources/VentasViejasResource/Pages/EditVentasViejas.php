<?php

namespace App\Filament\Admin\Resources\VentasViejasResource\Pages;

use App\Filament\Admin\Resources\VentasViejasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVentasViejas extends EditRecord
{
    protected static string $resource = VentasViejasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
