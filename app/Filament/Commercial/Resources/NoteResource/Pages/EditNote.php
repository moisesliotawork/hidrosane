<?php

namespace App\Filament\Commercial\Resources\NoteResource\Pages;

use App\Filament\Commercial\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Observation;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;
    public function getTitle(): string
    {
        return 'Nro de Nota: ' . $this->record->nro_nota;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Obtener el customer relacionado
        $note = $this->record;
        $customer = $note->customer;

        // Obtener las observaciones existentes
        $observations = $note->observations()->get()->map(function ($observation) {
            return [
                'id' => $observation->id, // Asegúrate de incluir el ID para edición
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
            'observations' => $observations, // Asegúrate de incluir este campo
        ]);
    }

    protected function afterSave(): void
    {
        $currentObservationIds = [];
        $observations = $this->data['observations'] ?? [];

        foreach ($observations as $observationData) {
            if (empty($observationData['observation'])) {
                continue;
            }

            if (isset($observationData['id'])) {
                $observation = $this->record->observations()->find($observationData['id']);
                if ($observation) {
                    $observation->update([
                        'observation' => $observationData['observation'],
                    ]);
                    $currentObservationIds[] = $observation->id;
                }
            } else {
                $newObservation = $this->record->observations()->create([
                    'author_id' => auth()->id(),
                    'observation' => $observationData['observation'],
                ]);
                $currentObservationIds[] = $newObservation->id;
            }
        }

        $this->record->observations()
            ->whereNotIn('id', $currentObservationIds)
            ->delete();
    }
}