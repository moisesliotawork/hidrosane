<?php

namespace App\Filament\Teleoperator\Resources\OficinaEditResource\Pages;

use App\Filament\Teleoperator\Resources\OficinaEditResource;
use App\Models\Customer;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditOficinaEdit extends EditRecord
{
    protected static string $resource = OficinaEditResource::class;

    public function getTitle(): string
    {
        return 'Editando Nota: ' . $this->record->nro_nota;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Nota guardada correctamente';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $note = $this->record;
        $customer = $note->customer;

        $observations = $note->observations()->get()->map(function ($observation) {
            return [
                'id' => $observation->id,
                'author_id' => $observation->author_id,
                'observation' => $observation->observation,
            ];
        })->toArray();

        return array_merge($data, [
            'first_names' => $customer->first_names,
            'last_names' => $customer->last_names,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'email' => $customer->email,
            'postal_code' => $customer->postal_code,
            'ciudad' => $customer->ciudad,
            'nro_piso' => $customer->nro_piso,
            'provincia' => $customer->provincia,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'edadTelOp' => $customer->edadTelOp,
            'observations' => $observations,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['phone'] = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));

        $sec = preg_replace('/\D+/', '', (string) ($data['secondary_phone'] ?? ''));
        $data['secondary_phone'] = $sec === '' ? null : $sec;

        $customer = Customer::findOrFail($data['customer_id']);

        $numerosAValidar = collect([
            $data['phone'] ?? null,
            $data['secondary_phone'] ?? null,
        ])->filter();

        $duplicados = [];

        foreach ($numerosAValidar as $numero) {
            $existe = Customer::query()
                ->where('id', '!=', $customer->id)
                ->where(function ($q) use ($numero) {
                    $q->where('phone', $numero)
                        ->orWhere('secondary_phone', $numero)
                        ->orWhere('third_phone', $numero)
                        ->orWhere('phone1_commercial', $numero)
                        ->orWhere('phone2_commercial', $numero);
                })
                ->exists();

            if ($existe) {
                $duplicados[] = $numero;
            }
        }

        if (!empty($duplicados)) {
            Notification::make()
                ->title('Teléfono(s) ya registrado(s)')
                ->body(
                    'Los siguientes números ya están registrados en la base de datos: ' .
                    implode(', ', $duplicados) .
                    '. No se puede guardar la nota con teléfonos duplicados.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'phone' => 'Números de teléfono duplicados: ' . implode(', ', $duplicados),
            ]);
        }

        $customer->update([
            'first_names' => $data['first_names'],
            'last_names' => $data['last_names'],
            'phone' => $data['phone'],
            'secondary_phone' => $data['secondary_phone'] ?? null,
            'email' => $data['email'],
            'postal_code' => $data['postal_code'],
            'nro_piso' => $data['nro_piso'],
            'ciudad' => $data['ciudad'],
            'provincia' => $data['provincia'],
            'primary_address' => $data['primary_address'],
            'secondary_address' => $data['secondary_address'] ?? null,
            'edadTelOp' => $data['edadTelOp'] ?? null,
        ]);

        unset(
            $data['first_names'],
            $data['last_names'],
            $data['phone'],
            $data['secondary_phone'],
            $data['email'],
            $data['postal_code'],
            $data['ciudad'],
            $data['provincia'],
            $data['nro_piso'],
            $data['primary_address'],
            $data['secondary_address'],
            $data['edadTelOp'],
        );

        return $data;
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
