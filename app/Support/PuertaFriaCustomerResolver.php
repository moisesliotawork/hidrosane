<?php

namespace App\Support;

use App\Models\Customer;
use App\Services\CustomerMergeService;
use Illuminate\Support\Collection;

/**
 * Resuelve el cliente al grabar una venta puerta fría.
 *
 * - Varios clientes pueden compartir teléfono si el nombre es distinto.
 * - Mismo teléfono + mismo nombre → reutiliza/fusiona.
 * - Mismo DNI (ambos informados) → reutiliza/fusiona (un solo DNI por persona).
 * - Nombre parecido con DNI distinto → permite crear otro cliente.
 */
class PuertaFriaCustomerResolver
{
    public function __construct(
        protected TeleoperatorCustomerNoteGuard $phoneGuard,
        protected CustomerMergeService $mergeService,
    ) {}

    /**
     * @param  array<string, mixed>  $customerPayload  Campos fillable de Customer
     */
    public function resolveOrCreate(array $customerPayload, ?int $mergedByUserId = null): Customer
    {
        $payload = $this->normalizePhoneFields($customerPayload);

        $existingId = filled($payload['pf_existing_customer_id'] ?? null)
            ? (int) $payload['pf_existing_customer_id']
            : null;

        unset($payload['pf_existing_customer_id']);

        $dni = $this->normalizeDni($payload['dni'] ?? null);

        if ($dni !== null) {
            $payload['dni'] = $dni;
        }

        $phoneDigits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($payload['phone1_commercial'] ?? null);
        $firstNames = trim((string) ($payload['first_names'] ?? ''));
        $lastNames = trim((string) ($payload['last_names'] ?? ''));

        if ($existingId !== null) {
            $keeper = Customer::query()->findOrFail($existingId);
        } elseif ($dni !== null) {
            $keeper = $this->findByDni($dni);
        } elseif ($phoneDigits !== null) {
            $keeper = $this->findByPhoneAndName($phoneDigits, $firstNames, $lastNames);

            if ($keeper === null && $this->phoneGuard->resolveCustomersForPhone($phoneDigits)->isNotEmpty()) {
                return Customer::create($payload);
            }
        } else {
            $keeper = null;
        }

        if ($keeper === null) {
            $keeper = $this->findByNameOnlyRespectingDni($firstNames, $lastNames, $dni);
        }

        if ($keeper === null) {
            return Customer::create($payload);
        }

        if ($dni !== null) {
            $keeper = $this->mergeSameDniDuplicates($keeper, $dni, $mergedByUserId);
        }

        $keeper = $this->mergeSamePhoneAndNameDuplicates($keeper, $phoneDigits, $firstNames, $lastNames, $mergedByUserId);

        $toUpdate = array_filter($payload, fn ($value) => $value !== null && $value !== '');

        if ($toUpdate !== []) {
            $keeper->fill($toUpdate)->save();
        }

        return $keeper->fresh();
    }

    protected function findByDni(string $dni): ?Customer
    {
        return $this->customersWithDni($dni)->first();
    }

    /**
     * @return Collection<int, Customer>
     */
    protected function customersWithDni(string $dni): Collection
    {
        return Customer::query()
            ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    protected function findByPhoneAndName(?string $phoneDigits, string $firstNames, string $lastNames): ?Customer
    {
        if ($phoneDigits === null) {
            return null;
        }

        return $this->phoneGuard
            ->resolveCustomersForPhone($phoneDigits)
            ->filter(fn (Customer $customer): bool => PuertaFriaCustomerSearch::hasSameFullNameFromParts(
                $customer,
                $firstNames,
                $lastNames,
            ))
            ->sortBy(fn (Customer $customer) => [
                optional($customer->created_at)->timestamp ?? PHP_INT_MAX,
                $customer->id,
            ])
            ->first();
    }

    protected function findByNameOnlyRespectingDni(string $firstNames, string $lastNames, ?string $formDni): ?Customer
    {
        $fullName = trim("{$firstNames} {$lastNames}");

        if ($fullName === '') {
            return null;
        }

        return PuertaFriaCustomerSearch::resolveCustomersForName($fullName)
            ->first(fn (Customer $customer): bool => $this->nameMatchAllowedByDni($customer, $formDni));
    }

    protected function nameMatchAllowedByDni(Customer $customer, ?string $formDni): bool
    {
        $customerDni = $this->normalizeDni($customer->dni);

        if ($formDni !== null && $customerDni !== null && $formDni !== $customerDni) {
            return false;
        }

        return true;
    }

    protected function mergeSameDniDuplicates(
        Customer $keeper,
        string $dni,
        ?int $mergedByUserId,
    ): Customer {
        foreach ($this->customersWithDni($dni) as $duplicate) {
            if ($duplicate->id === $keeper->id) {
                continue;
            }

            $this->mergeService->mergeByIds($keeper->id, $duplicate->id, $mergedByUserId);
            $keeper = $keeper->fresh();
        }

        return $keeper;
    }

    protected function mergeSamePhoneAndNameDuplicates(
        Customer $keeper,
        ?string $phoneDigits,
        string $firstNames,
        string $lastNames,
        ?int $mergedByUserId,
    ): Customer {
        if ($phoneDigits === null) {
            return $keeper;
        }

        $duplicates = $this->phoneGuard
            ->resolveCustomersForPhone($phoneDigits)
            ->filter(fn (Customer $customer) => $customer->id !== $keeper->id)
            ->filter(fn (Customer $customer) => PuertaFriaCustomerSearch::hasSameFullNameFromParts(
                $customer,
                $firstNames,
                $lastNames,
            ));

        foreach ($duplicates as $duplicate) {
            $this->mergeService->mergeByIds($keeper->id, $duplicate->id, $mergedByUserId);
            $keeper = $keeper->fresh();
        }

        return $keeper;
    }

    protected function normalizeDni(mixed $dni): ?string
    {
        $normalized = mb_strtoupper(trim((string) $dni));

        return filled($normalized) ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePhoneFields(array $payload): array
    {
        foreach (TeleoperatorCustomerNoteGuard::PHONE_COLUMNS as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($payload[$field]);

            if ($digits !== null) {
                $payload[$field] = $digits;
            }
        }

        return $payload;
    }
}
