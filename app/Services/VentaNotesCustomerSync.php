<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Venta;
use Illuminate\Support\Collection;

class VentaNotesCustomerSync
{
    /**
     * Alinea la nota del contrato (y asociados -B) con el cliente actualizado.
     *
     * Las demás notas del mismo cliente no se asocian al contrato: si ya comparten
     * el mismo customer_id, verán nombre y DNI nuevos al actualizarse el registro
     * Customer en el formulario (1 contrato = 1 nota origen).
     */
    public static function syncFromVenta(Venta $venta): void
    {
        $venta->loadMissing(['customer', 'note', 'asociadas', 'asociadasConmigo']);

        if (! $venta->customer_id) {
            return;
        }

        $noteIds = static::collectNoteIdsForVenta($venta);
        if ($noteIds->isNotEmpty()) {
            Note::query()
                ->whereIn('id', $noteIds)
                ->where('customer_id', '!=', $venta->customer_id)
                ->update(['customer_id' => $venta->customer_id]);
        }

        static::collectVentaIdsForVenta($venta)
            ->each(function (int $ventaId) use ($venta) {
                Venta::query()
                    ->whereKey($ventaId)
                    ->where('customer_id', '!=', $venta->customer_id)
                    ->update(['customer_id' => $venta->customer_id]);
            });
    }

    /** @return Collection<int, int> */
    protected static function collectNoteIdsForVenta(Venta $venta): Collection
    {
        $ids = collect([$venta->note_id]);

        foreach ($venta->todasAsociadas() as $asociada) {
            $ids->push($asociada->note_id);
        }

        return $ids->filter()->unique()->values();
    }

    /** @return Collection<int, int> */
    protected static function collectVentaIdsForVenta(Venta $venta): Collection
    {
        $ids = collect([$venta->id]);

        foreach ($venta->todasAsociadas() as $asociada) {
            $ids->push($asociada->id);
        }

        return $ids->filter()->unique()->values();
    }
}
