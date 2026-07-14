<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Note;
use App\Models\Venta;
use App\Support\Filament\FechaNacimientoField;

class VentaCustomerIdentityService
{
    /**
     * Desvincula el contrato del cliente compartido cuando hace falta:
     * - Cliente compartido + cambio de nombre/DNI → cliente nuevo.
     * - Cliente compartido + otros datos (fecha_nac, teléfono, etc.) → mismo registro Customer.
     * - Cliente exclusivo + cambio de nombre/DNI → otro cliente (por DNI o nuevo).
     */
    public static function reassignCustomerIfNeeded(Venta $venta, array &$data): void
    {
        $customerData = $data['customer'] ?? null;

        if (! is_array($customerData) || ! $venta->customer_id) {
            return;
        }

        $venta->loadMissing(['customer', 'asociadas', 'asociadasConmigo']);

        $originalCustomer = $venta->customer;
        if (! $originalCustomer) {
            return;
        }

        if (! static::customerDataChanged($originalCustomer, $customerData)) {
            return;
        }

        if (static::customerIsShared($venta) && static::identityChanged($originalCustomer, $customerData)) {
            $targetCustomer = static::resolveTargetCustomer($customerData, $originalCustomer->id);
            static::applyCustomerPayload($targetCustomer, $customerData);
            $data['customer_id'] = $targetCustomer->id;
            unset($data['customer']);

            return;
        }

        if (static::identityChanged($originalCustomer, $customerData)) {
            $targetCustomer = static::resolveTargetCustomer($customerData, $originalCustomer->id);
            static::applyCustomerPayload($targetCustomer, $customerData);
            $data['customer_id'] = $targetCustomer->id;
            unset($data['customer']);

            return;
        }

        static::applyCustomerPayload($originalCustomer, $customerData);
        unset($data['customer']);
    }

    public static function customerIsShared(Venta $venta): bool
    {
        if (! $venta->customer_id) {
            return false;
        }

        $venta->loadMissing(['asociadas', 'asociadasConmigo']);

        $ventaIds = collect([$venta->id])
            ->merge($venta->todasAsociadas()->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        $noteIds = collect([$venta->note_id])
            ->merge($venta->todasAsociadas()->pluck('note_id'))
            ->filter()
            ->unique()
            ->values();

        if (Venta::query()
            ->where('customer_id', $venta->customer_id)
            ->whereNotIn('id', $ventaIds)
            ->exists()) {
            return true;
        }

        return Note::query()
            ->where('customer_id', $venta->customer_id)
            ->whereNotIn('id', $noteIds)
            ->exists();
    }

    public static function identityChanged(Customer $original, array $newData): bool
    {
        return static::normalizeDni($original->dni) !== static::normalizeDni($newData['dni'] ?? null)
            || static::normalizeName($original->first_names) !== static::normalizeName($newData['first_names'] ?? null)
            || static::normalizeName($original->last_names) !== static::normalizeName($newData['last_names'] ?? null);
    }

    public static function customerDataChanged(Customer $original, array $newData): bool
    {
        foreach ((new Customer)->getFillable() as $field) {
            if (! array_key_exists($field, $newData)) {
                continue;
            }

            $old = static::normalizeFieldValue($field, $original->{$field});
            $new = static::normalizeFieldValue($field, $newData[$field]);

            if ($old !== $new) {
                return true;
            }
        }

        return false;
    }

    protected static function resolveTargetCustomer(array $customerData, int $excludeCustomerId): Customer
    {
        $dni = static::normalizeDni($customerData['dni'] ?? null);

        if (filled($dni)) {
            $existing = Customer::query()
                ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
                ->whereKeyNot($excludeCustomerId)
                ->whereNull('merged_into_id')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
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
            ->map(function (mixed $value, string $field): mixed {
                if ($field === 'fecha_nac') {
                    return FechaNacimientoField::normalizeForStorage($value);
                }

                return $value;
            })
            ->reject(fn ($value) => $value === null)
            ->all();
    }

    protected static function normalizeFieldValue(string $field, mixed $value): string
    {
        if ($field === 'fecha_nac') {
            if ($value instanceof \DateTimeInterface) {
                $value = FechaNacimientoField::toStorageDateString($value);
            }

            return FechaNacimientoField::normalizeForStorage($value) ?? '';
        }

        if (in_array($field, [
            'phone',
            'secondary_phone',
            'third_phone',
            'phone1_commercial',
            'phone2_commercial',
        ], true)) {
            return preg_replace('/\D+/', '', (string) $value);
        }

        if ($field === 'dni') {
            return static::normalizeDni($value);
        }

        if (in_array($field, ['first_names', 'last_names'], true)) {
            return static::normalizeName($value);
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return mb_strtolower(trim((string) $value));
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
