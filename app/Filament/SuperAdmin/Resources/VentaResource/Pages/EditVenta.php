<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource\Pages\EditVenta as BaseEditVenta;
use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\Customer;
use Filament\Notifications\Notification;

class EditVenta extends BaseEditVenta
{
    protected static string $resource = VentaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Guardado')
            ->body('Los cambios del contrato se han guardado correctamente.')
            ->duration(6000);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Guardado';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->pendingCustomerId) {
            return parent::mutateFormDataBeforeSave($data);
        }

        $newCustomerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        $oldCustomerId = (int) $this->record->customer_id;

        if ($newCustomerId && $newCustomerId !== $oldCustomerId) {
            Customer::query()->findOrFail($newCustomerId);
            unset($data['customer']);
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
