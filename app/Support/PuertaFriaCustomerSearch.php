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
     *     phone_digits: ?string
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
            ];
        }

        $customers = app(TeleoperatorCustomerNoteGuard::class)
            ->resolveCustomersForPhone($digits)
            ->unique('id')
            ->values();

        if ($customers->isEmpty()) {
            return [
                'customers' => collect(),
                'message' => 'No se encontró ningún cliente con ese teléfono. Puedes crear uno nuevo.',
                'phone_digits' => $digits,
            ];
        }

        $nameTerm = trim((string) $name);

        if ($nameTerm !== '') {
            $ranked = $customers
                ->map(fn (Customer $customer): array => [
                    'customer' => $customer,
                    'score' => self::nameSimilarityScore($customer, $nameTerm),
                ])
                ->sortByDesc('score')
                ->values();

            $withSimilarName = $ranked
                ->filter(fn (array $row): bool => $row['score'] >= 45)
                ->pluck('customer');

            if ($withSimilarName->isNotEmpty()) {
                return [
                    'customers' => $withSimilarName->values(),
                    'message' => 'Existe un cliente con ese teléfono y un nombre parecido. Selecciónalo o crea uno nuevo.',
                    'phone_digits' => $digits,
                ];
            }

            return [
                'customers' => $customers,
                'message' => 'Existe al menos un cliente con ese teléfono, pero el nombre no coincide del todo. Revisa la lista o crea uno nuevo.',
                'phone_digits' => $digits,
            ];
        }

        return [
            'customers' => $customers,
            'message' => 'Existe al menos un cliente con ese teléfono. Selecciónalo o crea uno nuevo.',
            'phone_digits' => $digits,
        ];
    }

    public static function displayName(Customer $customer): string
    {
        return mb_strtoupper(trim("{$customer->first_names} {$customer->last_names}"), 'UTF-8');
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
            'fecha_nac' => $customer->fecha_nac?->format('Y-m-d'),
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
