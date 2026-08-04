<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class LedgerNameMatcher
{
    public static function expectsVenta(string $estado, ?float $importe): bool
    {
        if (in_array($estado, [
            'NULO_FINANCIERO',
            'NULO_REPARTO',
            'DESISTIMIENTO',
            'NULO_AUSENTE',
            'NO_SALE',
        ], true)) {
            return false;
        }

        return $estado === 'VENTA' || $importe !== null;
    }

    public static function normalizeName(string $name): string
    {
        $name = Str::ascii(mb_strtoupper(trim($name)));
        $name = preg_replace('/[^A-Z0-9\s]/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * @return array{
     *   status: string,
     *   customer_id: int|null,
     *   customer_name: string|null,
     *   venta_id: int|null,
     *   nro_contr_adm: string|null,
     *   venta_deleted: bool|null,
     *   candidates: string
     * }
     */
    public static function match(string $clienteNombre, string $monthYyyymm, ?float $importe): array
    {
        $norm = self::normalizeName($clienteNombre);
        if ($norm === '') {
            return self::result('sin_cliente');
        }

        $tokens = array_values(array_filter(explode(' ', $norm), fn ($t) => mb_strlen($t) >= 3));
        if ($tokens === []) {
            return self::result('sin_cliente');
        }

        $customers = Customer::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->where(function ($qq) use ($token) {
                        $qq->whereRaw(
                            "UPPER(CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,''))) LIKE ?",
                            ['%'.$token.'%']
                        );
                    });
                }
            })
            ->limit(25)
            ->get(['id', 'first_names', 'last_names']);

        $scored = $customers->map(function (Customer $c) use ($norm) {
            $full = self::normalizeName(trim(($c->first_names ?? '').' '.($c->last_names ?? '')));
            similar_text($norm, $full, $pct);

            return [
                'customer' => $c,
                'full' => $full,
                'score' => (float) $pct,
            ];
        })->sortByDesc('score')->values();

        $good = $scored->filter(fn ($r) => $r['score'] >= 55)->values();
        if ($good->isEmpty()) {
            return self::result('sin_cliente');
        }

        $top = $good->first();
        $close = $good->filter(fn ($r) => $r['score'] >= ($top['score'] - 8))->values();
        if ($close->count() > 1 && ($close[1]['score'] ?? 0) >= 70) {
            return self::result('ambiguo', candidates: $close->take(5)->map(
                fn ($r) => $r['customer']->id.':'.$r['full'].'('.round($r['score']).')'
            )->implode('|'));
        }

        /** @var Customer $customer */
        $customer = $top['customer'];
        $start = Carbon::createFromFormat('Ym', $monthYyyymm)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $ventas = Venta::withTrashed()
            ->where('customer_id', $customer->id)
            ->whereBetween('fecha_venta', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('id')
            ->get(['id', 'nro_contr_adm', 'importe_total', 'deleted_at']);

        if ($ventas->isEmpty()) {
            return self::result(
                'cliente_sin_venta',
                customerId: (int) $customer->id,
                customerName: $top['full'],
            );
        }

        $picked = self::pickVenta($ventas, $importe);
        if ($picked === null) {
            return self::result(
                'cliente_sin_venta',
                customerId: (int) $customer->id,
                customerName: $top['full'],
                candidates: $ventas->map(fn (Venta $v) => $v->id.':'.$v->nro_contr_adm)->implode('|'),
            );
        }

        return self::result(
            'match',
            customerId: (int) $customer->id,
            customerName: $top['full'],
            ventaId: (int) $picked->id,
            nro: (string) $picked->nro_contr_adm,
            deleted: $picked->trashed(),
        );
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     */
    protected static function pickVenta(Collection $ventas, ?float $importe): ?Venta
    {
        if ($importe === null) {
            return $ventas->first();
        }

        $best = null;
        $bestDiff = PHP_FLOAT_MAX;
        foreach ($ventas as $v) {
            $diff = abs((float) $v->importe_total - $importe);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $v;
            }
        }

        if ($best !== null && $bestDiff <= max(50.0, $importe * 0.15)) {
            return $best;
        }

        return $ventas->first();
    }

    /**
     * @return array{
     *   status: string,
     *   customer_id: int|null,
     *   customer_name: string|null,
     *   venta_id: int|null,
     *   nro_contr_adm: string|null,
     *   venta_deleted: bool|null,
     *   candidates: string
     * }
     */
    protected static function result(
        string $status,
        ?int $customerId = null,
        ?string $customerName = null,
        ?int $ventaId = null,
        ?string $nro = null,
        ?bool $deleted = null,
        string $candidates = '',
    ): array {
        return [
            'status' => $status,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'venta_id' => $ventaId,
            'nro_contr_adm' => $nro,
            'venta_deleted' => $deleted,
            'candidates' => $candidates,
        ];
    }
}
