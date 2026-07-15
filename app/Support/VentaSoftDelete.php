<?php

namespace App\Support;

use App\Models\Venta;

final class VentaSoftDelete
{
    public static function delete(Venta $venta, ?int $deletedByUserId = null): void
    {
        $venta->forceFill([
            'deleted_by_user_id' => $deletedByUserId ?? auth()->id(),
        ])->saveQuietly();

        $venta->delete();
    }
}
