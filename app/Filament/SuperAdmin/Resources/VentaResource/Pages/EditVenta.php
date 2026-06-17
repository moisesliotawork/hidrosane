<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource\Pages\EditVenta as BaseEditVenta;
use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\Customer;

class EditVenta extends BaseEditVenta
{
    protected static string $resource = VentaResource::class;

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
