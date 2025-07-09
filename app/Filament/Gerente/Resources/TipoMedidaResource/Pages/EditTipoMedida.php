<?php

namespace App\Filament\Gerente\Resources\TipoMedidaResource\Pages;

use App\Filament\Gerente\Resources\TipoMedidaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoMedida extends EditRecord
{
    protected static string $resource = TipoMedidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
