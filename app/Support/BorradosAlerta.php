<?php

namespace App\Support;

use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * La alerta roja de contratos borrados se puede descartar.
 * El contrato sigue en Contratos borrados; solo deja de avisar.
 */
final class BorradosAlerta
{
    public static function enabled(): bool
    {
        return Schema::hasColumn('ventas', 'alerta_borrado_vista_at');
    }

    public static function pendingQuery(): Builder
    {
        $query = Venta::onlyTrashed()->enContratosBorrados();

        if (self::enabled()) {
            $query->whereNull('alerta_borrado_vista_at');
        }

        return $query;
    }

    public static function pendingCount(): int
    {
        return (int) self::pendingQuery()->count();
    }

    public static function acknowledgePending(): int
    {
        if (! self::enabled()) {
            return 0;
        }

        return self::pendingQuery()->update([
            'alerta_borrado_vista_at' => now(),
        ]);
    }
}
