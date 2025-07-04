<?php

namespace App\Filament\Gerente\Resources\NoteResource\Pages;

use App\Filament\Gerente\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Customer;
use App\Models\PostalCode;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Obtener el customer relacionado
        $note = $this->record;
        $customer = $note->customer;

        // Obtener las observaciones existentes
        $observations = $note->observations()->get()->map(function ($observation) {
            return [
                'author_id' => $observation->author_id,
                'observation' => $observation->observation,
                // Agregar campos adicionales si es necesario
            ];
        })->toArray();

        // Combinar los datos de la nota con los del customer y observaciones
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
        // Verificar que el postal_code_id existe
        $postalCode = PostalCode::find($data['postal_code_id']);
        if (!$postalCode) {
            throw new \Exception("El código postal seleccionado no existe");
        }

        // Actualizar el customer con los nuevos datos
        $customer = Customer::find($data['customer_id']);
        $customer->update([
            'first_names' => $data['first_names'],
            'last_names' => $data['last_names'],
            'phone' => $data['phone'],
            'secondary_phone' => $data['secondary_phone'] ?? null,
            'email' => $data['email'],
            'postal_code_id' => $postalCode->id,
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

    protected function afterSave(): void
    {
        $currentObservationIds = [];
        $observations = $this->data['observations'] ?? [];

        foreach ($observations as $observationData) {
            // Ignorar observaciones vacías
            if (empty($observationData['observation'])) {
                continue;
            }

            if (isset($observationData['id'])) {
                // Actualizar observación existente
                $observation = $this->record->observations()->find($observationData['id']);
                if ($observation) {
                    $observation->update([
                        'observation' => $observationData['observation'],
                    ]);
                    $currentObservationIds[] = $observation->id;
                }
            } else {
                // Crear nueva observación
                $newObservation = $this->record->observations()->create([
                    'author_id' => auth()->id(), // O usa el dato del form si quieres
                    'observation' => $observationData['observation'],
                ]);
                $currentObservationIds[] = $newObservation->id;
            }
        }

        // Eliminar observaciones que ya no existen en el formulario
        $this->record->observations()
            ->whereNotIn('id', $currentObservationIds)
            ->delete();
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
