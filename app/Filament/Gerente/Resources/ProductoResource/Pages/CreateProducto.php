<?php

namespace App\Filament\Gerente\Resources\ProductoResource\Pages;

use App\Filament\Gerente\Resources\ProductoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Producto;
use App\Models\ProductoMedida;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;

    protected function handleRecordCreation(array $data): Producto
    {
        // Crear producto
        $producto = Producto::create([
            'nombre' => $data['nombre'],
            'puntos' => $data['puntos'],
        ]);

        // Si hay tipo de medida y valor, guardamos medida
        if (!empty($data['tipo_medida_id']) && !empty($data['valor'])) {
            ProductoMedida::create([
                'producto_id' => $producto->id,
                'tipo_medida_id' => $data['tipo_medida_id'],
                'valor' => $data['valor'],
            ]);
        }

        return $producto;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
