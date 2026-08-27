<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CustomerDuplicateSearchService
{
    public const PHONE_FIELDS = [
        'phone',
        'secondary_phone',
        'third_phone',
        'phone1_commercial',
        'phone2_commercial',
    ];

    public const SESSION_KEY = 'duplicados_customer_ids';

    /**
     * Clientes activos que pertenecen a un grupo de al menos 2 registros con:
     * - mismo DNI (no vacío) y nombre compatible, o
     * - mismo nombre completo y al menos un teléfono compartido.
     */
    public static function findDuplicateIds(): array
    {
        return array_values(array_unique(array_merge(
            static::findDniDuplicateIds(),
            static::findPhoneDuplicateIds(),
        )));
    }

    /** @return list<int> */
    public static function refreshDuplicateIdsInSession(): array
    {
        $ids = static::findDuplicateIds();
        static::storeDuplicateIdsInSession($ids);

        return $ids;
    }

    public static function storeDuplicateIdsInSession(array $ids): void
    {
        session([static::SESSION_KEY => $ids]);
    }

    /** @return list<int> */
    public static function duplicateIdsFromSession(): array
    {
        $ids = session(static::SESSION_KEY, []);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    public static function duplicateIdsSubquery(): QueryBuilder
    {
        $ids = static::duplicateIdsFromSession();

        if ($ids === []) {
            return DB::table('customers')->select('id')->whereRaw('0 = 1');
        }

        return DB::table('customers')->select('id')->whereIn('id', $ids);
    }

    public static function applySearchScope(Builder $query): Builder
    {
        $ids = static::duplicateIdsFromSession();

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('id', $ids);
    }

    public static function countDuplicates(): int
    {
        return count(static::duplicateIdsFromSession());
    }

    public static function orderByLatestActivitySql(): string
    {
        return "
            GREATEST(
                COALESCE((SELECT MAX(fecha_venta) FROM ventas WHERE ventas.customer_id = customers.id), '1970-01-01'),
                COALESCE((SELECT MAX(assignment_date) FROM notes WHERE notes.customer_id = customers.id), '1970-01-01'),
                COALESCE((SELECT MAX(notes.created_at) FROM notes WHERE notes.customer_id = customers.id), '1970-01-01'),
                customers.created_at
            ) DESC
        ";
    }

    public static function orderByClientNameSql(string $direction = 'ASC'): string
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return "TRIM(CONCAT(COALESCE(first_names, ''), ' ', COALESCE(last_names, ''))) {$direction}";
    }

    /**
     * Pares fusionables: exactamente 2 clientes activos, mismo nombre completo y al menos un teléfono compartido.
     *
     * @return list<array{
     *     pair_key: string,
     *     name: string,
     *     keeper_id: int,
     *     to_delete_id: int,
     *     shared_phones: list<string>,
     *     customers: list<array<string, mixed>>
     * }>
     */
    public static function findAutoMergePairsOfTwo(): array
    {
        $edges = static::findStrictNameSharedPhonePairEdges();
        $components = static::connectedComponentsFromEdges($edges);

        $pairs = [];

        foreach ($components as $component) {
            if (count($component) !== 2) {
                continue;
            }

            $customers = \App\Models\Customer::query()
                ->withCount(['notes', 'ventas'])
                ->whereIn('id', $component)
                ->whereNull('merged_into_id')
                ->get();

            if ($customers->count() !== 2) {
                continue;
            }

            $sorted = $customers
                ->sortBy(fn (\App\Models\Customer $customer) => [
                    optional($customer->created_at)->timestamp ?? PHP_INT_MAX,
                    $customer->id,
                ])
                ->values();

            /** @var \App\Models\Customer $keeper */
            $keeper = $sorted->first();
            /** @var \App\Models\Customer $toDelete */
            $toDelete = $sorted->last();

            $pairs[] = static::formatAutoMergePair($keeper, $toDelete);
        }

        usort(
            $pairs,
            fn (array $left, array $right) => strcasecmp($left['name'], $right['name'])
        );

        return $pairs;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $edges
     * @return list<list<int>>
     */
    public static function connectedComponentsFromEdges(array $edges): array
    {
        $adjacency = [];

        foreach ($edges as [$leftId, $rightId]) {
            $adjacency[$leftId][] = $rightId;
            $adjacency[$rightId][] = $leftId;
        }

        $visited = [];
        $components = [];

        foreach (array_keys($adjacency) as $nodeId) {
            if (isset($visited[$nodeId])) {
                continue;
            }

            $stack = [$nodeId];
            $component = [];

            while ($stack !== []) {
                $current = array_pop($stack);

                if (isset($visited[$current])) {
                    continue;
                }

                $visited[$current] = true;
                $component[] = $current;

                foreach ($adjacency[$current] ?? [] as $neighborId) {
                    if (! isset($visited[$neighborId])) {
                        $stack[] = $neighborId;
                    }
                }
            }

            sort($component);
            $components[] = $component;
        }

        return $components;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public static function findStrictNameSharedPhonePairEdges(): array
    {
        $phonesUnion = static::customerPhonesUnionSql();

        $sharedPhones = DB::query()
            ->fromRaw("{$phonesUnion} AS shared_cp")
            ->select('shared_cp.phone')
            ->groupBy('shared_cp.phone')
            ->havingRaw('COUNT(DISTINCT shared_cp.customer_id) > 1');

        $rows = DB::query()
            ->fromRaw("{$phonesUnion} AS cp1")
            ->join(DB::raw("{$phonesUnion} AS cp2"), function ($join) {
                $join->on('cp1.phone', '=', 'cp2.phone')
                    ->whereColumn('cp1.customer_id', '<', 'cp2.customer_id');
            })
            ->joinSub($sharedPhones, 'shared_phones', 'shared_phones.phone', '=', 'cp1.phone')
            ->join('customers as c1', 'c1.id', '=', 'cp1.customer_id')
            ->join('customers as c2', 'c2.id', '=', 'cp2.customer_id')
            ->whereNull('c1.merged_into_id')
            ->whereNull('c2.merged_into_id')
            ->where(function ($query) {
                static::applyStrictFullNameMatch($query);
            })
            ->selectRaw('cp1.customer_id AS id_a, cp2.customer_id AS id_b')
            ->get();

        return $rows
            ->map(fn ($row) => [(int) $row->id_a, (int) $row->id_b])
            ->unique(fn (array $pair) => $pair[0].'-'.$pair[1])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     pair_key: string,
     *     name: string,
     *     keeper_id: int,
     *     to_delete_id: int,
     *     shared_phones: list<string>,
     *     customers: list<array<string, mixed>>
     * }
     */
    protected static function formatAutoMergePair(
        \App\Models\Customer $keeper,
        \App\Models\Customer $toDelete,
    ): array {
        $sharedPhones = static::sharedPhonesBetween($keeper, $toDelete);
        $name = mb_strtoupper(trim($keeper->first_names.' '.$keeper->last_names));

        return [
            'pair_key' => min($keeper->id, $toDelete->id).'-'.max($keeper->id, $toDelete->id),
            'name' => $name,
            'keeper_id' => $keeper->id,
            'to_delete_id' => $toDelete->id,
            'shared_phones' => $sharedPhones,
            'customers' => [
                static::formatCustomerRow($keeper, 'keeper'),
                static::formatCustomerRow($toDelete, 'merge'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function sharedPhonesBetween(
        \App\Models\Customer $left,
        \App\Models\Customer $right,
    ): array {
        $leftPhones = collect(static::phonesFromCustomer($left));
        $rightPhones = collect(static::phonesFromCustomer($right));

        return $leftPhones->intersect($rightPhones)->sort()->values()->all();
    }

    /**
     * @return list<string>
     */
    public static function phonesFromCustomer(\App\Models\Customer $customer): array
    {
        $phones = [];

        foreach (self::PHONE_FIELDS as $field) {
            $digits = preg_replace('/\D+/', '', (string) ($customer->{$field} ?? ''));

            if (strlen($digits) === 9) {
                $phones[] = $digits;
            }
        }

        return array_values(array_unique($phones));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function formatCustomerRow(\App\Models\Customer $customer, string $role): array
    {
        return [
            'id' => $customer->id,
            'role' => $role,
            'name' => mb_strtoupper(trim($customer->first_names.' '.$customer->last_names)),
            'phone' => static::formatPhoneDisplay($customer->phone),
            'secondary_phone' => static::formatPhoneDisplay($customer->secondary_phone),
            'third_phone' => static::formatPhoneDisplay($customer->third_phone),
            'dni' => $customer->dni ?: '—',
            'notes_count' => (int) $customer->notes_count,
            'ventas_count' => (int) $customer->ventas_count,
            'created_at' => optional($customer->created_at)?->format('d/m/Y H:i') ?? '—',
        ];
    }

    public static function formatPhoneDisplay(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) !== 9) {
            return '—';
        }

        return implode(' ', str_split($digits, 3));
    }

    /** @return list<int> */
    protected static function findDniDuplicateIds(): array
    {
        $duplicateDnis = DB::table('customers')
            ->select('dni')
            ->whereNull('merged_into_id')
            ->whereNotNull('dni')
            ->where('dni', '!=', '')
            ->groupBy('dni')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('dni');

        if ($duplicateDnis->isEmpty()) {
            return [];
        }

        $base = DB::table('customers as c1')
            ->join('customers as c2', function ($join) {
                $join->on('c1.dni', '=', 'c2.dni')
                    ->whereColumn('c1.id', '<', 'c2.id');
            })
            ->whereNull('c1.merged_into_id')
            ->whereNull('c2.merged_into_id')
            ->whereIn('c1.dni', $duplicateDnis)
            ->where(function ($query) {
                static::applyNameMatchConditions($query);
            });

        return $base->clone()
            ->select('c1.id')
            ->pluck('c1.id')
            ->merge($base->clone()->select('c2.id')->pluck('c2.id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    protected static function findPhoneDuplicateIds(): array
    {
        $phonesUnion = static::customerPhonesUnionSql();

        $sharedPhones = DB::query()
            ->fromRaw("{$phonesUnion} AS shared_cp")
            ->select('shared_cp.phone')
            ->groupBy('shared_cp.phone')
            ->havingRaw('COUNT(DISTINCT shared_cp.customer_id) > 1');

        $base = DB::query()
            ->fromRaw("{$phonesUnion} AS cp1")
            ->join(DB::raw("{$phonesUnion} AS cp2"), function ($join) {
                $join->on('cp1.phone', '=', 'cp2.phone')
                    ->whereColumn('cp1.customer_id', '<', 'cp2.customer_id');
            })
            ->joinSub($sharedPhones, 'shared_phones', 'shared_phones.phone', '=', 'cp1.phone')
            ->join('customers as c1', 'c1.id', '=', 'cp1.customer_id')
            ->join('customers as c2', 'c2.id', '=', 'cp2.customer_id')
            ->whereNull('c1.merged_into_id')
            ->whereNull('c2.merged_into_id')
            ->where(function ($query) {
                static::applyStrictFullNameMatch($query);
            });

        return $base->clone()
            ->select('cp1.customer_id')
            ->pluck('cp1.customer_id')
            ->merge($base->clone()->select('cp2.customer_id')->pluck('cp2.customer_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected static function customerPhonesUnionSql(): string
    {
        $parts = array_map(
            fn (string $field) => "SELECT id AS customer_id, TRIM(COALESCE(`{$field}`, '')) AS phone "
                . 'FROM customers '
                . 'WHERE merged_into_id IS NULL '
                . "AND NULLIF(TRIM(COALESCE(`{$field}`, '')), '') IS NOT NULL",
            self::PHONE_FIELDS,
        );

        return '('.implode(' UNION ALL ', $parts).')';
    }

    protected static function normalizedFullNameSql(string $alias): string
    {
        return "TRIM(LOWER(CONCAT(COALESCE({$alias}.first_names,''),' ',COALESCE({$alias}.last_names,''))))";
    }

    protected static function applyNameMatchConditions($query): void
    {
        $full1 = static::normalizedFullNameSql('c1');
        $full2 = static::normalizedFullNameSql('c2');

        $query->where(function ($nameQuery) use ($full1, $full2) {
            $nameQuery
                ->whereRaw("{$full1} != '' AND {$full1} = {$full2}")
                ->orWhere(function ($q) {
                    $q->whereRaw("TRIM(LOWER(COALESCE(c1.first_names,''))) != ''")
                        ->whereRaw("TRIM(LOWER(COALESCE(c1.first_names,''))) = TRIM(LOWER(COALESCE(c2.first_names,'')))");
                })
                ->orWhere(function ($q) {
                    $q->whereRaw("TRIM(LOWER(COALESCE(c1.last_names,''))) != ''")
                        ->whereRaw("TRIM(LOWER(COALESCE(c1.last_names,''))) = TRIM(LOWER(COALESCE(c2.last_names,'')))");
                })
                ->orWhere(function ($q) use ($full1, $full2) {
                    $q->whereRaw("{$full1} != '' AND {$full2} != ''")
                        ->where(function ($containsQuery) use ($full1, $full2) {
                            $containsQuery
                                ->whereRaw("{$full1} LIKE CONCAT('%', {$full2}, '%')")
                                ->orWhereRaw("{$full2} LIKE CONCAT('%', {$full1}, '%')");
                        });
                });
        });
    }

    protected static function applyStrictFullNameMatch($query): void
    {
        $full1 = static::normalizedFullNameSql('c1');
        $full2 = static::normalizedFullNameSql('c2');

        $query->whereRaw("{$full1} != '' AND {$full1} = {$full2}");
    }
}
