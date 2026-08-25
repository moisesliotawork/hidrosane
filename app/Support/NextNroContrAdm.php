<?php

namespace App\Support;

/**
 * Siguiente nro_contr_adm para altas comerciales.
 *
 * MAX() sobre el string elige "999" frente a "2304", y el alta acaba en 01000
 * (colisiona con el contrato 1000). Aquí se usa el máximo numérico.
 */
final class NextNroContrAdm
{
    public const FIRST_AUTO = 1023;

    /**
     * @param  iterable<int|string|null>  $existing
     */
    public static function fromExisting(iterable $existing): string
    {
        $max = 0;
        $taken = [];

        foreach ($existing as $nro) {
            $int = self::titularInteger($nro);
            if ($int === null) {
                continue;
            }

            $taken[$int] = true;
            if ($int > $max) {
                $max = $int;
            }
        }

        $next = $max === 0 ? self::FIRST_AUTO : $max + 1;
        while (isset($taken[$next])) {
            $next++;
        }

        return (string) $next;
    }

    public static function titularInteger(int|string|null $nro): ?int
    {
        $nro = trim((string) $nro);
        if ($nro === '' || ! preg_match('/^0*(\d+)/', $nro, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
