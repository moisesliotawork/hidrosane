<?php

namespace App\Support;

use App\Models\ContratoMesVariacionItem;
use App\Models\Venta;

final class VentaSoftDelete
{
    public static function delete(Venta $venta, ?int $deletedByUserId = null): void
    {
        $venta->forceFill([
            'deleted_by_user_id' => $deletedByUserId ?? auth()->id(),
        ])->saveQuietly();

        $venta->delete();

        $archived = Venta::withTrashed()->find($venta->id) ?? $venta;

        ContratosPorMesStats::recordVariationItem(
            $archived,
            ContratoMesVariacionItem::ESTADO_SOFT_DELETE,
            $deletedByUserId ?? auth()->id(),
        );
    }
}
