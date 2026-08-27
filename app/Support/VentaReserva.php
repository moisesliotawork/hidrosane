<?php

namespace App\Support;

use App\Models\Venta;

final class VentaReserva
{
    public static function move(Venta $venta, ?int $userId = null): void
    {
        $userId ??= auth()->id();

        if (! $venta->trashed()) {
            VentaSoftDelete::delete($venta, $userId);
            $venta = Venta::withTrashed()->findOrFail($venta->id);
        }

        $venta->forceFill([
            'reservado_at' => now(),
            'reservado_by_user_id' => $userId,
        ])->saveQuietly();
    }

    public static function moveAllFromBorrados(?int $userId = null): int
    {
        $userId ??= auth()->id();
        $count = 0;

        Venta::onlyTrashed()
            ->whereNull('reservado_at')
            ->orderBy('id')
            ->each(function (Venta $venta) use ($userId, &$count): void {
                static::move($venta, $userId);
                $count++;
            });

        return $count;
    }
}
