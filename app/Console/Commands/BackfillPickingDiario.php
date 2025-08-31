<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPickingDiario extends Command
{
    protected $signature = 'picking:backfill {--from=} {--to=}';
    protected $description = 'Rellena picking_diario agrupando TODAS las ventas por (fecha, producto).';

    public function handle(): int
    {
        $dateExpr = "DATE(v.fecha_entrega)";

        $whereRange = '';
        $from = $this->option('from');
        $to = $this->option('to');

        if ($from && $to) {
            $whereRange = " AND {$dateExpr} BETWEEN ? AND ? ";
        } elseif ($from) {
            $whereRange = " AND {$dateExpr} >= ? ";
        } elseif ($to) {
            $whereRange = " AND {$dateExpr} <= ? ";
        }

        // 🔴 Vaciar la tabla antes de volver a llenarla
        DB::table('picking_diario')->truncate();

        $sql = "
            INSERT INTO picking_diario (fecha, producto_id, cantidad_total, entregado, entregado_at, entregado_by, created_at, updated_at)
            SELECT
                {$dateExpr} AS fecha,
                vop.producto_id,
                SUM(vop.cantidad) AS cantidad_total,
                0 AS entregado,
                NULL AS entregado_at,
                NULL AS entregado_by,
                NOW() AS created_at,
                NOW() AS updated_at
            FROM venta_oferta_productos vop
            INNER JOIN venta_ofertas vo ON vo.id = vop.venta_oferta_id
            INNER JOIN ventas v ON v.id = vo.venta_id
            WHERE v.fecha_entrega IS NOT NULL
            {$whereRange}
            GROUP BY fecha, vop.producto_id
        ";

        $bindings = [];
        if ($from && $to) {
            $bindings = [$from, $to];
        } elseif ($from) {
            $bindings = [$from];
        } elseif ($to) {
            $bindings = [$to];
        }

        $this->info('Vaciando y poblando picking_diario (solo fecha_entrega)…');
        DB::statement($sql, $bindings);
        $this->info('Listo. ✅');

        return self::SUCCESS;
    }
}
