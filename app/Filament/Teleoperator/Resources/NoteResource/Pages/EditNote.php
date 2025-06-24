<?php

namespace App\Filament\Teleoperator\Resources\NoteResource\Pages;

use App\Filament\Teleoperator\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Customer;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Obtener el customer relacionado
        $note = $this->record;
        $customer = $note->customer;

        // Combinar los datos de la nota con los del customer
        return array_merge($data, [
            'first_names' => $customer->first_names,
            'last_names' => $customer->last_names,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'email' => $customer->email,
            'postal_code_id' => $customer->postal_code_id,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'parish' => $customer->parish,
            'age' => $customer->age,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Actualizar el customer con los nuevos datos
        $customer = Customer::find($data['customer_id']);
        $customer->update([
            'first_names' => $data['first_names'],
            'last_names' => $data['last_names'],
            'phone' => $data['phone'],
            'secondary_phone' => $data['secondary_phone'] ?? null,
            'email' => $data['email'],
            'postal_code_id' => $data['postal_code_id'],
            'primary_address' => $data['primary_address'],
            'secondary_address' => $data['secondary_address'] ?? null,
            'parish' => $data['parish'] ?? null,
            'age' => $data['age'] ?? null,
        ]);

        // Eliminar los campos del customer del array de datos de la nota
        unset(
            $data['first_names'],
            $data['last_names'],
            $data['phone'],
            $data['secondary_phone'],
            $data['email'],
            $data['postal_code_id'],
            $data['primary_address'],
            $data['secondary_address'],
            $data['parish'],
            $data['age'],
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
