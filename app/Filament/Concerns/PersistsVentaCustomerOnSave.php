<?php

namespace App\Filament\Concerns;

use App\Services\VentaCustomerIdentityService;
use Illuminate\Support\Arr;

/**
 * Evita guardar el cliente varias veces en el mismo submit de Contratos.
 * beforeSave persiste con el estado del formulario; mutateFormDataBeforeSave
 * no debe repetir tras loadStateFromRelationships (riesgo de fecha ISO corrupta).
 */
trait PersistsVentaCustomerOnSave
{
    protected bool $ventaCustomerPersistedInBeforeSave = false;

    protected function persistVentaCustomerInBeforeSave(): void
    {
        $customerData = $this->data['customer'] ?? null;

        if (! is_array($customerData) || ! $this->record->customer_id) {
            return;
        }

        $payload = [
            'customer_id' => $this->record->customer_id,
            'customer' => $customerData,
        ];

        $previousCustomerId = (int) $this->record->customer_id;

        VentaCustomerIdentityService::reassignCustomerIfNeeded($this->record, $payload);

        $this->ventaCustomerPersistedInBeforeSave = true;

        if ((int) ($payload['customer_id'] ?? 0) === $previousCustomerId) {
            return;
        }

        $this->pendingCustomerId = (int) $payload['customer_id'];
        $this->record->customer_id = $this->pendingCustomerId;
        $this->record->saveQuietly();
        $this->record->unsetRelation('customer');
    }

    protected function stripVentaCustomerFromSavePayload(array &$data): void
    {
        if ($this->pendingCustomerId) {
            $data['customer_id'] = $this->pendingCustomerId;
            unset($data['customer']);

            return;
        }

        if ($this->ventaCustomerPersistedInBeforeSave) {
            unset($data['customer']);

            return;
        }

        $data['customer'] = $data['customer'] ?? Arr::get($this->data, 'customer');
        VentaCustomerIdentityService::reassignCustomerIfNeeded($this->record, $data);
        unset($data['customer']);
    }
}
