<?php

namespace App\Support;

use App\Models\Venta;
use Carbon\Carbon;

class VentaFechaVenta
{
    public static function timezone(): string
    {
        return 'Europe/Madrid';
    }

    /** Hora legible en listados (recupera created_at si fecha_venta es medianoche). */
    public static function horaDisplay(Venta $venta): string
    {
        $dt = self::resolveDateTime($venta);

        return $dt ? $dt->timezone(self::timezone())->format('H:i') : '--';
    }

    public static function resolveDateTime(Venta $venta): ?Carbon
    {
        $fecha = $venta->fecha_venta;

        if (! $fecha) {
            return $venta->created_at?->copy()->timezone(self::timezone());
        }

        $fecha = $fecha instanceof Carbon
            ? $fecha->copy()->timezone(self::timezone())
            : Carbon::parse($fecha, self::timezone());

        if (self::isMidnight($fecha) && $venta->created_at) {
            $created = $venta->created_at->copy()->timezone(self::timezone());

            return $fecha->setTime(
                (int) $created->format('H'),
                (int) $created->format('i'),
                (int) $created->format('s'),
            );
        }

        return $fecha;
    }

    /** Al crear: DatePicker sin hora → hora actual. */
    public static function normalizeOnCreate(mixed $incoming = null): Carbon
    {
        $now = now(self::timezone());

        if (blank($incoming)) {
            return $now;
        }

        $parsed = Carbon::parse($incoming, self::timezone());

        if (self::isMidnight($parsed)) {
            return $parsed->setTime(
                (int) $now->format('H'),
                (int) $now->format('i'),
                (int) $now->format('s'),
            );
        }

        return $parsed;
    }

    /** Al editar: conservar hora previa si el formulario solo envía la fecha. */
    public static function normalizeOnSave(mixed $incoming, ?Venta $existing = null): Carbon
    {
        $previous = $existing?->fecha_venta;

        if (blank($incoming)) {
            return $previous
                ? $previous->copy()->timezone(self::timezone())
                : now(self::timezone());
        }

        $parsed = Carbon::parse($incoming, self::timezone());

        if (! self::isMidnight($parsed)) {
            return $parsed;
        }

        if ($previous && ! self::isMidnight($previous)) {
            $prev = $previous->copy()->timezone(self::timezone());

            return $parsed->setTime(
                (int) $prev->format('H'),
                (int) $prev->format('i'),
                (int) $prev->format('s'),
            );
        }

        if ($existing?->created_at) {
            $created = $existing->created_at->copy()->timezone(self::timezone());

            return $parsed->setTime(
                (int) $created->format('H'),
                (int) $created->format('i'),
                (int) $created->format('s'),
            );
        }

        return self::normalizeOnCreate($parsed);
    }

    private static function isMidnight(Carbon $dt): bool
    {
        return $dt->format('H:i:s') === '00:00:00';
    }
}
