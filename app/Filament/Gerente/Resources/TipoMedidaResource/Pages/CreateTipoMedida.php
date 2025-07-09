<?php

namespace App\Filament\Gerente\Resources\TipoMedidaResource\Pages;

use App\Filament\Gerente\Resources\TipoMedidaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoMedida extends CreateRecord
{
    protected static string $resource = TipoMedidaResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
