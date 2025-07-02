<?php

namespace App\Filament\Gerente\Resources\NoteResource\Pages;

use App\Filament\Gerente\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Customer;
use App\Models\Observation;
use App\Models\PostalCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            // Botón principal (Guardar)
            Actions\CreateAction::make()
                ->label('Guardar')
                ->action('create'),

            // Botón secundario (Guardar y crear otro)
            Actions\CreateAction::make('createAnother')
                ->label('Guardar y crear otro')
                ->color("gray")
                ->action('createAnother'),

            // Botón Cancelar
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('danger')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Normalizar los nombres para comparación
        $normalizedFirstName = Str::slug(Str::lower($data['first_names']), '');
        $normalizedLastName = Str::slug(Str::lower($data['last_names']), '');

        // Buscar cliente existente
        $customer = Customer::query()
            ->whereRaw("LOWER(REPLACE(first_names, ' ', '')) = ?", [$normalizedFirstName])
            ->whereRaw("LOWER(REPLACE(last_names, ' ', '')) = ?", [$normalizedLastName])
            ->where('phone', $data['phone'])
            ->first();

        // Verificar que el postal_code_id existe
        $postalCode = PostalCode::find($data['postal_code_id']);
        if (!$postalCode) {
            throw new \Exception("El código postal seleccionado no existe");
        }

        if ($customer) {
            // Actualizar solo los campos permitidos (excluyendo nombres y teléfono)
            $customer->update([
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                'email' => $data['email'] ?? $customer->email,
                'postal_code_id' => $postalCode->id,
                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,
                'parish' => $data['parish'] ?? $customer->parish,
                'age' => $data['age'] ?? $customer->age,
            ]);
        } else {
            // Crear nuevo cliente con todos los datos
            $customer = Customer::create([
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
        }

        // Asignar los IDs necesarios
        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        $observations = $this->form->getState()['observations'] ?? [];

        foreach ($observations as $observationData) {
            // Verifica si existe y tiene contenido
            if (!empty($observationData['observation'])) {
                Observation::create([
                    'note_id' => $this->record->id,
                    'author_id' => auth()->id(),
                    'observation' => $observationData['observation'],
                ]);
            }
        }
    }
}
