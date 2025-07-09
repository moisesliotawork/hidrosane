<?php

namespace App\Filament\Admin\Resources\TipoMedidaResource\Pages;

use App\Filament\Admin\Resources\TipoMedidaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoMedidas extends ListRecords
{
    protected static string $resource = TipoMedidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
