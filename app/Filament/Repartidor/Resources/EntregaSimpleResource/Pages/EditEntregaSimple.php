<?php

namespace App\Filament\Repartidor\Resources\EntregaSimpleResource\Pages;

use App\Filament\Repartidor\Resources\EntregaSimpleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEntregaSimple extends EditRecord
{
    protected static string $resource = EntregaSimpleResource::class;

    public function getTitle(): string
    {
        return 'DATOS DEL CONTRATO ' . $this->record->nro_contrato;
    }
}
