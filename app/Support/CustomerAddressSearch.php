<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda parcial de domicilio del cliente.
 * El UI muestra address + nro_piso + ciudad; el LIKE solo sobre primary_address
 * falla con términos tipo "VILACHAN 7".
 */
class CustomerAddressSearch
{
    /**
     * @return list<string>
     */
    public static function tokens(string $search): array
    {
        $normalized = mb_strtolower(trim($search));

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($parts));
    }

    /**
     * Aplica AND de tokens: cada token debe aparecer en alguno de los campos de domicilio.
     *
     * @param  Builder<\App\Models\Customer>|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainCustomerQuery($query, string $search): void
    {
        $tokens = self::tokens($search);

        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            $term = '%'.$token.'%';

            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(primary_address, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(nro_piso, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(secondary_address, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(ciudad, \'\')) LIKE ?', [$term]);
            });
        }
    }

    /**
     * Restringe un query de Note vía whereHas('customer').
     *
     * @param  Builder<\App\Models\Note>  $query
     * @return Builder<\App\Models\Note>
     */
    public static function constrainHasCustomer(Builder $query, string $search): Builder
    {
        if (trim($search) === '') {
            return $query;
        }

        return $query->whereHas('customer', function (Builder $q) use ($search) {
            self::constrainCustomerQuery($q, $search);
        });
    }
}
