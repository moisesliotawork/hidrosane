<?php

namespace App\Filament\Gerente\Resources\SupervisionResource\Pages;

use App\Filament\Gerente\Resources\SupervisionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Supervision;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class CreateSupervision extends CreateRecord
{
    protected static string $resource = SupervisionResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = auth()->id();

        // Unicidad: supervisor_id + supervisado_id + start_date
        $exists = Supervision::query()
            ->where('supervisor_id', $data['supervisor_id'])
            ->where('supervisado_id', $data['supervisado_id'])
            ->whereDate('start_date', $data['start_date'])
            ->exists();

        if ($exists) {
            // 🔔 Notificación visual en Filament
            Notification::make()
                ->title('Duplicado detectado')
                ->body('Ya existe una supervisión con este Supervisor, Supervisado y Fecha de inicio.')
                ->danger()
                ->send();

            // También mostramos error de validación en el form
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe una supervisión con este Supervisor, Supervisado y Fecha de inicio.',
            ]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
