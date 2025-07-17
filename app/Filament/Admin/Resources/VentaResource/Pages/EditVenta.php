<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /* Garantiza que nunca intentamos cambiar el nro_nota */
        unset($data['note']['nro_nota']);
        return $data;
    }
}
