<?php

namespace App\Support;

use App\Models\ContratoRecoveryItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros compartidos entre la tabla RECUPERADOS ACEPTADOS y su PDF.
 */
class RecoveredContractsQuery
{
    public static function fechaSqlExpression(): string
    {
        return "COALESCE(
            NULLIF(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(contrato_recovery_items.reviewed_json, '$.fecha_venta')), '%Y-%m-%d'), '0000-00-00'),
            NULLIF(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(contrato_recovery_items.reviewed_json, '$.fecha_venta')), '%d/%m/%Y'), '0000-00-00'),
            NULLIF(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(contrato_recovery_items.reviewed_json, '$.fecha_venta')), '%d-%m-%Y'), '0000-00-00'),
            (SELECT v.fecha_venta FROM ventas v WHERE v.id = contrato_recovery_items.venta_id LIMIT 1)
        )";
    }

    /**
     * @return Builder<ContratoRecoveryItem>
     */
    public static function base(): Builder
    {
        return ContratoRecoveryItem::query()->latest('id');
    }

    /**
     * @param  Builder<ContratoRecoveryItem>  $query
     * @return Builder<ContratoRecoveryItem>
     */
    public static function applyMonthFilter(Builder $query, ?string $yearMonth, bool $showAll): Builder
    {
        if ($showAll || blank($yearMonth)) {
            return $query;
        }

        try {
            [$year, $month] = array_map('intval', explode('-', $yearMonth));
        } catch (\Throwable) {
            return $query;
        }

        $fechaExpr = self::fechaSqlExpression();

        return $query->whereRaw("YEAR({$fechaExpr}) = ? AND MONTH({$fechaExpr}) = ?", [$year, $month]);
    }

    /**
     * Misma lógica que la búsqueda global de la tabla Filament.
     *
     * @param  Builder<ContratoRecoveryItem>  $query
     * @return Builder<ContratoRecoveryItem>
     */
    public static function applySearchFilter(Builder $query, ?string $search): Builder
    {
        $q = trim((string) $search);
        if ($q === '') {
            return $query;
        }

        $like = '%'.$q.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('cliente_nombre', 'like', $like)
                ->orWhere('dni', 'like', $like)
                ->orWhere('nro_contr_adm', 'like', $like)
                ->orWhere('reviewed_json->nro_contr_adm', 'like', $like)
                ->orWhereHas('customer', function (Builder $customerQuery) use ($like): void {
                    $customerQuery->where('first_names', 'like', $like)
                        ->orWhere('last_names', 'like', $like)
                        ->orWhere('dni', 'like', $like);
                })
                ->orWhereHas('venta', function (Builder $ventaQuery) use ($like): void {
                    $ventaQuery->where('nro_contr_adm', 'like', $like)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($like): void {
                            $customerQuery->where('first_names', 'like', $like)
                                ->orWhere('last_names', 'like', $like)
                                ->orWhere('dni', 'like', $like);
                        });
                });
        });
    }

    /**
     * @return Builder<ContratoRecoveryItem>
     */
    public static function forList(?string $yearMonth, bool $showAll, ?string $search = null): Builder
    {
        $query = self::base();
        self::applyMonthFilter($query, $yearMonth, $showAll);
        self::applySearchFilter($query, $search);

        return $query;
    }
}
