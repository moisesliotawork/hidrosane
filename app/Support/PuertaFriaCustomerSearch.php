<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PuertaFriaCustomerSearch
{
    /**
     * @return array{
     *     customers: Collection<int, Customer>,
     *     message: string,
     *     phone_digits: ?string,
     *     status: 'invalid_phone'|'found_by_phone'|'found_by_name'|'not_found'
     * }
     */
    public static function search(?string $phone, ?string $name = null): array
    {
        $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($phone);

        if ($digits === null) {
            return [
                'customers' => collect(),
                'message' => 'Introduce un teléfono válido de 9 dígitos.',
                'phone_digits' => null,
                'status' => 'invalid_phone',
            ];
        }

        $nameTerm = trim((string) $name);

        $customersByPhone = app(TeleoperatorCustomerNoteGuard::class)
            ->resolveCustomersForPhone($digits)
            ->unique('id')
            ->values();

        if ($customersByPhone->isNotEmpty()) {
            $hasSimilarName = $nameTerm !== '' && $customersByPhone->contains(
                fn (Customer $customer): bool => self::nameSimilarityScore($customer, $nameTerm) >= 45,
            );

            if ($hasSimilarName) {
                $message = 'Se encontró un cliente con ese teléfono y nombre coincidente. Selecciónalo para crear el contrato con ese cliente o crea uno nuevo.';
            } elseif ($nameTerm !== '') {
                $message = 'Se encontró un cliente con ese teléfono, aunque el nombre no coincide. Selecciónalo para crear el contrato con ese cliente o crea uno nuevo.';
            } else {
                $message = 'Se encontró un cliente con ese teléfono. Selecciónalo para crear el contrato con ese cliente o crea uno nuevo.';
            }

            return [
                'customers' => $customersByPhone,
                'message' => $message,
                'phone_digits' => $digits,
                'status' => 'found_by_phone',
            ];
        }

        if ($nameTerm !== '') {
            $customersByName = self::resolveCustomersForName($nameTerm);

            if ($customersByName->isNotEmpty()) {
                return [
                    'customers' => $customersByName,
                    'message' => 'Se encontró un cliente con un nombre parecido. Selecciónalo para crear el contrato con ese cliente o crea uno nuevo.',
                    'phone_digits' => $digits,
                    'status' => 'found_by_name',
                ];
            }
        }

        return [
            'customers' => collect(),
            'message' => 'Cliente no encontrado con esos datos, puedes crear un nuevo contrato asociado a un NUEVO CLIENTE',
            'phone_digits' => $digits,
            'status' => 'not_found',
        ];
    }

    /**
     * @return Collection<int, Customer>
     */
    public static function resolveCustomersForName(string $nameTerm): Collection
    {
        $nameTerm = trim(preg_replace('/\s+/u', ' ', $nameTerm));

        if ($nameTerm === '') {
            return collect();
        }

        ['first_names' => $firstName, 'last_names' => $lastName] = self::splitLookupName($nameTerm);

        $candidates = Customer::query()
            ->where(function ($query) use ($firstName, $lastName, $nameTerm): void {
                if ($firstName !== '') {
                    $query->where('first_names', 'like', "%{$firstName}%");
                }

                if ($lastName !== '') {
                    $query->orWhere('last_names', 'like', "%{$lastName}%");
                }

                $query->orWhereRaw(
                    "CONCAT(COALESCE(first_names, ''), ' ', COALESCE(last_names, '')) LIKE ?",
                    ["%{$nameTerm}%"],
                );
            })
            ->limit(50)
            ->get();

        return $candidates
            ->map(fn (Customer $customer): array => [
                'customer' => $customer,
                'score' => self::nameSimilarityScore($customer, $nameTerm),
            ])
            ->filter(fn (array $row): bool => $row['score'] >= 45)
            ->sortByDesc('score')
            ->pluck('customer')
            ->unique('id')
            ->values();
    }

    public static function displayName(Customer $customer): string
    {
        return mb_strtoupper(trim("{$customer->first_names} {$customer->last_names}"), 'UTF-8');
    }

    public static function primaryPhoneDigits(Customer $customer): ?string
    {
        foreach (TeleoperatorCustomerNoteGuard::PHONE_COLUMNS as $column) {
            $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($customer->{$column});

            if ($digits !== null) {
                return $digits;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerToFormData(Customer $customer, ?int $existingCustomerId = null): array
    {
        $formatPhone = function (?string $value): ?string {
            $digits = preg_replace('/\D+/', '', (string) $value);

            if (strlen($digits) !== 9) {
                return $value;
            }

            return implode(' ', str_split($digits, 3));
        };

        return [
            'pf_existing_customer_id' => $existingCustomerId ?? $customer->id,
            'first_names' => $customer->first_names,
            'last_names' => $customer->last_names,
            'dni' => $customer->dni,
            'fecha_nac' => $customer->safeFechaNac()?->format('Y-m-d'),
            'phone1_commercial' => $formatPhone($customer->phone1_commercial ?: $customer->phone),
            'phone2_commercial' => $formatPhone($customer->phone2_commercial ?: $customer->secondary_phone),
            'email' => $customer->email,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'nro_piso' => $customer->nro_piso,
            'postal_code' => $customer->postal_code,
            'ciudad' => $customer->ciudad,
            'provincia' => $customer->provincia,
            'tipo_vivienda' => $customer->tipo_vivienda,
            'estado_civil' => $customer->estado_civil,
            'situacion_laboral' => $customer->situacion_laboral,
            'antiguedad' => $customer->antiguedad,
            'nombre_empresa' => $customer->nombre_empresa,
            'oficio' => $customer->oficio,
        ];
    }

    /**
     * @return array{first_names: string, last_names: string}
     */
    public static function splitLookupName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return ['first_names' => '', 'last_names' => ''];
        }

        $parts = explode(' ', $name, 2);

        return [
            'first_names' => $parts[0],
            'last_names' => $parts[1] ?? '',
        ];
    }

    public static function nameSimilarityScore(Customer $customer, string $term): int
    {
        $full = self::normalizeName("{$customer->first_names} {$customer->last_names}");
        $term = self::normalizeName($term);

        if ($full === '' || $term === '') {
            return 0;
        }

        if ($full === $term) {
            return 100;
        }

        if (str_contains($full, $term) || str_contains($term, $full)) {
            return 90;
        }

        $first = self::normalizeName((string) $customer->first_names);
        $last = self::normalizeName((string) $customer->last_names);

        if ($first !== '' && (str_contains($first, $term) || str_contains($term, $first))) {
            return 80;
        }

        if ($last !== '' && (str_contains($last, $term) || str_contains($term, $last))) {
            return 75;
        }

        similar_text($full, $term, $percent);

        return (int) round($percent);
    }

    private static function normalizeName(string $value): string
    {
        return Str::ascii(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8'));
    }
}
