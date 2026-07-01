<?php

namespace App\Services;

use App\Models\Customer;
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

    /**
     * Clientes activos con otro registro duplicado por:
     * - mismo DNI (no vacío) y nombre parcial o total igual, o
     * - mismo nombre parcial o total y al menos un teléfono compartido (cualquier campo).
     */
    public static function duplicateIdsSubquery(): QueryBuilder
    {
        return DB::table('customers as c1')
            ->select('c1.id')
            ->join('customers as c2', 'c1.id', '!=', 'c2.id')
            ->whereNull('c1.merged_into_id')
            ->whereNull('c2.merged_into_id')
            ->where(function ($query) {
                $query->where(function ($dniMatch) {
                    $dniMatch
                        ->whereRaw('c1.dni = c2.dni')
                        ->whereRaw("c1.dni IS NOT NULL AND c1.dni != ''")
                        ->where(function ($nameQuery) {
                            static::applyNameMatchConditions($nameQuery);
                        });
                })->orWhere(function ($phoneMatch) {
                    static::applyNameMatchConditions($phoneMatch);
                    static::applySharedPhoneConditions($phoneMatch);
                });
            })
            ->distinct();
    }

    public static function applySearchScope(Builder $query): Builder
    {
        return $query->whereIn('id', static::duplicateIdsSubquery());
    }

    public static function countDuplicates(): int
    {
        return Customer::query()
            ->whereIn('id', static::duplicateIdsSubquery())
            ->count();
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

    protected static function applySharedPhoneConditions($query): void
    {
        $query->where(function ($phoneQuery) {
            foreach (static::PHONE_FIELDS as $field1) {
                foreach (static::PHONE_FIELDS as $field2) {
                    $phoneQuery->orWhere(function ($pair) use ($field1, $field2) {
                        $pair->whereRaw("NULLIF(TRIM(COALESCE(c1.{$field1}, '')), '') IS NOT NULL")
                            ->whereRaw("TRIM(COALESCE(c1.{$field1}, '')) = TRIM(COALESCE(c2.{$field2}, ''))");
                    });
                }
            }
        });
    }
}
