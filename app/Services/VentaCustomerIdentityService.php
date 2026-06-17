<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Venta;

class VentaCustomerIdentityService
{
    /**
     * Si cambian nombre o DNI, el contrato pasa a otro cliente (nuevo o existente por DNI)
     * sin modificar el registro del cliente anterior.
     */
    public static function reassignCustomerIfIdentityChanged(Venta $venta, array &$data): void
    {
        $customerData = $data['customer'] ?? null;

        if (! is_array($customerData) || ! $venta->customer_id) {
            return;
        }

        $venta->loadMissing('customer');

        $originalCustomer = $venta->customer;
        if (! $originalCustomer) {
            return;
        }

        if (! static::identityChanged($originalCustomer, $customerData)) {
            return;
        }

        $targetCustomer = static::resolveTargetCustomer($customerData, $originalCustomer->id);
        static::applyCustomerPayload($targetCustomer, $customerData);

        $data['customer_id'] = $targetCustomer->id;
        unset($data['customer']);
    }

    public static function identityChanged(Customer $original, array $newData): bool
    {
        return static::normalizeDni($original->dni) !== static::normalizeDni($newData['dni'] ?? null)
            || static::normalizeName($original->first_names) !== static::normalizeName($newData['first_names'] ?? null)
            || static::normalizeName($original->last_names) !== static::normalizeName($newData['last_names'] ?? null);
    }

    protected static function resolveTargetCustomer(array $customerData, int $excludeCustomerId): Customer
    {
        $dni = static::normalizeDni($customerData['dni'] ?? null);

        if (filled($dni)) {
            $existing = Customer::query()
                ->where('dni', $dni)
                ->whereKeyNot($excludeCustomerId)
                ->whereNull('merged_into_id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Customer::create(static::extractCustomerPayload($customerData));
    }

    protected static function applyCustomerPayload(Customer $customer, array $customerData): void
    {
        $customer->update(static::extractCustomerPayload($customerData));
    }

    /** @return array<string, mixed> */
    protected static function extractCustomerPayload(array $customerData): array
    {
        $fillable = (new Customer)->getFillable();

        return collect($customerData)
            ->only($fillable)
            ->reject(fn ($value) => $value === null)
            ->all();
    }

    protected static function normalizeDni(?string $dni): string
    {
        return mb_strtoupper(trim((string) $dni));
    }

    protected static function normalizeName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }
}
