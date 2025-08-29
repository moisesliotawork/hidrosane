<?php

namespace App\Filament\Repartidor\Resources\EntregaSimpleResource\Pages;

use App\Filament\Repartidor\Resources\EntregaSimpleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEntregaSimple extends CreateRecord
{
    protected static string $resource = EntregaSimpleResource::class;

    /**
     * Creamos la Venta y de una vez persistimos/actualizamos su Reparto con los extras.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var \App\Models\Venta $venta */
        $venta = static::getModel()::create($data);

        // ⬅️ NECESARIO: guardar relaciones (customer, etc.)
        $this->form->model($venta)->saveRelationships();

        // Leemos toggles no deshidratados
        $state = $this->form->getState();
        $extras = $state['reparto_extras'] ?? [];

        $venta->reparto()->updateOrCreate(
            [],
            [
                'cliente_firma_garantias' => (bool) ($extras['cliente_firma_garantias'] ?? false),
                'cliente_comentario_goodwork' => (bool) ($extras['cliente_comentario_goodwork'] ?? false),
                'cliente_firma_digital' => (bool) ($extras['cliente_firma_digital'] ?? false),
            ]
        );

        return $venta;
    }

}
