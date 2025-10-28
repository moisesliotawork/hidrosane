<?php

namespace App\Filament\HeadOfRoom\Resources\NoteResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Observation;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
        // 1) Normalizar teléfonos (solo dígitos)
        $data['phone'] = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $sec = preg_replace('/\D+/', '', (string) ($data['secondary_phone'] ?? ''));
        $thr = preg_replace('/\D+/', '', (string) ($data['third_phone'] ?? ''));

        $data['secondary_phone'] = $sec === '' ? null : $sec;
        $data['third_phone'] = $thr === '' ? null : $thr;

        // 2) Normalizar nombres para comparación
        $normalizedFirstName = Str::slug(Str::lower($data['first_names']), '');
        $normalizedLastName = Str::slug(Str::lower($data['last_names']), '');

        // 3) Buscar cliente existente
        $customer = Customer::query()
            ->whereRaw("LOWER(REPLACE(first_names, ' ', '')) = ?", [$normalizedFirstName])
            ->whereRaw("LOWER(REPLACE(last_names, ' ', '')) = ?", [$normalizedLastName])
            ->where('phone', $data['phone'])
            ->first();

        // Calcular edad desde fecha_nac (si viene)
        $fechaNac = $data['fecha_nac'] ?? null;
        $computedAge = null;
        if ($fechaNac) {
            try {
                $computedAge = Carbon::parse($fechaNac)->age;
            } catch (\Throwable $e) {
                $computedAge = null;
            }
        }

        if ($customer) {
            $customer->update([
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                'email' => $data['email'] ?? $customer->email,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,
                'parish' => $data['parish'] ?? $customer->parish,
                'edadTelOp' => $data['edadTelOp'] ?? $customer->edadTelOp,
            ]);
        } else {
            $customer = Customer::create([
                'first_names' => $data['first_names'],
                'last_names' => $data['last_names'],
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? null,
                'secondary_address' => $data['secondary_address'] ?? null,
                'parish' => $data['parish'] ?? null,
                'edadTelOp' => $data['edadTelOp'] ?? null,
            ]);
        }

        // Asignar IDs en la Note
        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        // Importante: no guardar estos campos en notes si no existen allí
        unset($data['edadTelOp']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $observations = $this->form->getState()['observations'] ?? [];

        foreach ($observations as $observationData) {
            if (!empty($observationData['observation'])) {
                Observation::create([
                    'note_id' => $this->record->id,
                    'author_id' => $observationData['author_id'] ?? auth()->id(),
                    'observation' => $observationData['observation'],
                ]);
            }
        }
    }



}
