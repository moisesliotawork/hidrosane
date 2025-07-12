<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Venta;
use App\Models\PostalCode;
use App\Models\Note;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;


    /**
     * /ventas/create/{note}  ➜  $note llega aquí
     */
    public function mount(): void
    {
        parent::mount();

        /** ID que viene en /ventas/create/{note} */
        $noteId = request()->route('note');   // string|null

        abort_if(empty($noteId), 404, 'Nota no especificada');

        // 1. Cargar la nota con su cliente
        $this->note = Note::with('customer')->findOrFail((int) $noteId);
        $customer = $this->note->customer;

        // 2. Pre-rellenar el formulario
        $this->form->fill(array_merge(
            ['note_id' => $this->note->id],
            $customer->only($customer->getFillable())
        ));
    }



    protected function handleRecordCreation(array $data): Venta
    {
        /* 1. Validar que el código postal existe  ---------------------------- */
        $postalCode = PostalCode::find($data['postal_code_id']);
        if (!$postalCode) {
            throw new \Exception("El código postal seleccionado no existe");
        }

        /* 2. Actualizar cliente --------------------------------------------- */
        $customer = $this->note->customer;
        $customer->update(array_intersect_key(
            $data,
            array_flip($customer->getFillable())
        ));

        /* 3. Crear venta (pasa el id del CP a la venta si fuera necesario) -- */
        $venta = Venta::create([
            'note_id' => $this->note->id,
            'customer_id' => $customer->id,
            'fecha_venta' => $data['fecha_venta'],
            'importe_total' => $data['importe_total'],
            'num_cuotas' => $data['num_cuotas'] ?? null,
            'interes_art' => $data['interes_art'] ?? false,
        ]);

        /* 4. Guardar ofertas + productos ------------------------------------ */
        $this->form->model($venta)->saveRelationships();

        return $venta;
    }
}
