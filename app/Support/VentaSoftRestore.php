<?php

namespace App\Support;

use App\Models\ContratoMesVariacionItem;
use App\Models\Venta;

final class VentaSoftRestore
{
    public static function restore(Venta $venta): void
    {
        $venta->restore();

        $venta->forceFill([
            'deleted_by_user_id' => null,
        ])->saveQuietly();

        ContratosPorMesStats::recordVariationItem(
            $venta->fresh() ?? $venta,
            ContratoMesVariacionItem::ESTADO_RESTAURADO,
            auth()->id(),
        );
    }
}
