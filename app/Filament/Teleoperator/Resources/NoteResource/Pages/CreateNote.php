<?php

namespace App\Filament\Teleoperator\Resources\NoteResource\Pages;

use App\Filament\Teleoperator\Resources\NoteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use Illuminate\Support\Str;
use Filament\Actions;
use Filament\Actions\Action;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;

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
        // Normalizar los nombres para comparación (sin espacios y en minúsculas)
        $normalizedFirstName = Str::slug(Str::lower($data['first_names']), '');
        $normalizedLastName = Str::slug(Str::lower($data['last_names']), '');

        // Buscar cliente existente
        $customer = Customer::query()
            ->whereRaw("LOWER(REPLACE(first_names, ' ', '')) = ?", [$normalizedFirstName])
            ->whereRaw("LOWER(REPLACE(last_names, ' ', '')) = ?", [$normalizedLastName])
            ->where('phone', $data['phone'])
            ->first();

        // Si no existe, crear nuevo cliente
        if (!$customer) {
            $customer = Customer::create([
                'first_names' => $data['first_names'],
                'last_names' => $data['last_names'],
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'email' => $data['email'],
                'postal_code' => $data['postal_code'],
                'primary_address' => $data['primary_address'],
                'secondary_address' => $data['secondary_address'] ?? null,
                'parish' => $data['parish'] ?? null,
            ]);
        }

        // Asignar los IDs necesarios
        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        return $data;
    }
}