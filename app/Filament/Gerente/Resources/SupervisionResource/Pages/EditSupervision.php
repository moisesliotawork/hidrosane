<?php

namespace App\Filament\Gerente\Resources\SupervisionResource\Pages;

use App\Filament\Gerente\Resources\SupervisionResource;
use App\Models\Supervision;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class EditSupervision extends EditRecord
{
    protected static string $resource = SupervisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Mantener author_id original
        $data['author_id'] = $this->record->author_id;

        // Comprobar duplicado, ignorando el propio registro
        $exists = Supervision::query()
            ->where('supervisor_id', $data['supervisor_id'])
            ->where('supervisado_id', $data['supervisado_id'])
            ->whereDate('start_date', $data['start_date'])
            ->where('id', '!=', $this->record->id)
            ->exists();

        if ($exists) {
            // 🔔 Notificación en Filament
            Notification::make()
                ->title('Duplicado detectado')
                ->body('Ya existe otra supervisión con este Supervisor, Supervisado y Fecha de inicio.')
                ->danger()
                ->send();

            // Error de validación
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe otra supervisión con este Supervisor, Supervisado y Fecha de inicio.',
            ]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
