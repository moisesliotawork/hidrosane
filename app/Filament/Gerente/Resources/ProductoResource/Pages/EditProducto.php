<?php

namespace App\Filament\Gerente\Resources\ProductoResource\Pages;

use App\Filament\Gerente\Resources\ProductoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProducto extends EditRecord
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tipo_medida_id'], $data['valor']);
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // 1. Actualizar los campos propios del producto
        $record->update($data);

        // 2. Sincronizar la medida
        $tipoId = $this->form->getState()['tipo_medida_id'] ?? null;
        $valor = $this->form->getState()['valor'] ?? null;

        if ($tipoId && $valor) {
            // crea o actualiza la fila correspondiente
            $record->medidas()->updateOrCreate(
                ['tipo_medida_id' => $tipoId],
                ['valor' => $valor]
            );
        } else {
            // si vacías ambos campos, eliminamos la medida del producto
            $record->medidas()->delete();
        }

        return $record;
    }



    protected function mutateFormDataBeforeFill(array $data): array
    {
        $medida = $this->record->medidas()->first();   // relación hasMany

        if ($medida) {
            $data['tipo_medida_id'] = $medida->tipo_medida_id;
            $data['valor'] = $medida->valor;
        }

        return $data;
    }
}
