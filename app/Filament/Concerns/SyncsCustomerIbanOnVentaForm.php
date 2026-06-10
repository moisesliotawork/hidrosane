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

    protected function persistCustomerIban(array &$data): void
    {
        if (!array_key_exists('iban', $data)) {
            return;
        }

        CustomerIbanForm::persist($this->record?->customer, $data['iban']);
        unset($data['iban']);
    }
}
