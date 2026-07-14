<?php

namespace App\Filament\Concerns;

use App\Filament\Support\CustomerIbanForm;

trait SyncsCustomerIbanOnVentaForm
{
    protected function hydrateCustomerIban(array $data): array
    {
        $iban = $this->record?->customer?->iban;

        if ($iban !== null) {
            $data['iban'] = $iban;
        }

        return $data;
    }

    /**
     * Evita desfases de 1 día al hidratar relationship('customer'): la BD es la fuente de verdad.
     */
    protected function hydrateCustomerFechaNac(array $data): array
    {
        $customer = $this->record?->customer;

        if (! $customer) {
            return $data;
        }

        if (! isset($data['customer']) || ! is_array($data['customer'])) {
            $data['customer'] = [];
        }

        $stored = $customer->storedFechaNac();

        if ($stored !== null) {
            $data['customer']['fecha_nac'] = $stored;
        }

        return $data;
    }

    protected function hydrateCustomerFormData(array $data): array
    {
        return $this->hydrateCustomerFechaNac(
            $this->hydrateCustomerIban($data),
        );
    }

    protected function persistCustomerIban(array &$data): void
    {
        if (! array_key_exists('iban', $data)) {
            return;
        }

        $customerId = $data['customer_id'] ?? $this->record?->customer_id;
        $customer = $customerId
            ? \App\Models\Customer::query()->find($customerId)
            : $this->record?->customer;

        CustomerIbanForm::persist($customer, $data['iban']);
        unset($data['iban']);
    }
}
