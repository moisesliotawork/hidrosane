<?php

namespace App\Support;

use App\Models\ContratoRecoveryItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros compartidos entre la tabla RECUPERADOS ACEPTADOS y su PDF.
 */
class RecoveredContractsQuery
{
    /**
     * Fecha efectiva del recuperado (JSON → venta), sin literales '0000-00-00'
     * (MySQL estricto en prod lanza error 1525 con NULLIF(..., '0000-00-00')).
     */
    public static function fechaSqlExpression(): string
    {
        $rawJson = "NULLIF(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(contrato_recovery_items.reviewed_json, '$.fecha_venta'))), ''), 'null')";

        $parsed = "COALESCE(
            STR_TO_DATE({$rawJson}, '%Y-%m-%d'),
            STR_TO_DATE({$rawJson}, '%d/%m/%Y'),
            STR_TO_DATE({$rawJson}, '%d-%m-%Y'),
            (
                SELECT CASE
                    WHEN v.fecha_venta IS NULL THEN NULL
                    WHEN CAST(v.fecha_venta AS CHAR) LIKE '0000-%' THEN NULL
                    ELSE v.fecha_venta
                END
                FROM ventas v
                WHERE v.id = contrato_recovery_items.venta_id
                LIMIT 1
            )
        )";

        // Evita que YEAR()/MONTH() reciban fechas cero si STR_TO_DATE las devolviera.
        return "CASE
            WHEN {$parsed} IS NULL THEN NULL
            WHEN CAST({$parsed} AS CHAR) LIKE '0000-%' THEN NULL
            ELSE {$parsed}
        END";
    }

    /**
     * @return Builder<ContratoRecoveryItem>
     */
    public static function base(): Builder
    {
        return ContratoRecoveryItem::query()
            ->where('status', '!=', ContratoRecoveryItem::STATUS_REJECTED_EXISTS)
            ->latest('id');
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

        return $query->whereRaw(
            "({$fechaExpr}) IS NOT NULL AND YEAR({$fechaExpr}) = ? AND MONTH({$fechaExpr}) = ?",
            [$year, $month]
        );
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
