<?php

namespace App\Filament\Gerente\Resources\NoteResource\Pages;

use App\Filament\Gerente\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Customer;
use Carbon\Carbon;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $note = $this->record;
        $customer = $note->customer;

        // Observaciones existentes
        $observations = $note->observations()->get()->map(function ($observation) {
            return [
                'id' => $observation->id,
                'author_id' => $observation->author_id,
                'observation' => $observation->observation,
            ];
        })->toArray();

        // Fecha de nacimiento y edad calculada
        $fechaNac = $customer->fecha_nac ?? null;
        $computedAge = $fechaNac ? Carbon::parse($fechaNac)->age : null;

        return array_merge($data, [
            'first_names' => $customer->first_names,
            'last_names' => $customer->last_names,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'email' => $customer->email,
            'postal_code' => $customer->postal_code,
            'ciudad' => $customer->ciudad,
            'provincia' => $customer->provincia,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'parish' => $customer->parish,
            'edadTelOp' => $customer->edadTelOp,

            'observations' => $observations,
        ]);
    }


    protected function mutateFormDataBeforeSave(array $data): array
    {

        // Recalcular edad desde fecha_nac (ignorar age del form)
        $fechaNac = $data['fecha_nac'] ?? null;
        $computedAge = null;
        if ($fechaNac) {
            try {
                $computedAge = Carbon::parse($fechaNac)->age;
            } catch (\Throwable $e) {
                $computedAge = null;
            }
        }

        // Actualizar el customer
        $customer = Customer::find($data['customer_id']);
        $customer->update([
            'first_names' => $data['first_names'],
            'last_names' => $data['last_names'],
            'phone' => $data['phone'],
            'secondary_phone' => $data['secondary_phone'] ?? null,
            'email' => $data['email'],
            'postal_code' => $data['postal_code'],
            'ciudad' => $data['ciudad'],
            'provincia' => $data['provincia'],
            'primary_address' => $data['primary_address'],
            'secondary_address' => $data['secondary_address'] ?? null,
            'parish' => $data['parish'] ?? null,
            'edadTelOp' => $data['edadTelOp'] ?? null,
        ]);

        // Quitar del payload de Note los campos del Customer
        unset(
            $data['first_names'],
            $data['last_names'],
            $data['phone'],
            $data['secondary_phone'],
            $data['email'],
            $data['postal_code'],
            $data['ciudad'],
            $data['provincia'],
            $data['primary_address'],
            $data['secondary_address'],
            $data['parish'],
            $data['edadTelOp'],
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
