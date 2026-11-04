<?php

namespace App\Support;

/**
 * Apellidos exigidos al declarar una venta: dos palabras distintas.
 * Una palabra de menos de 3 caracteres no cuenta como apellido.
 */
class CustomerSurnames
{
    public const MESSAGE = 'Debes escribir al menos 2 apellidos distintos. El segundo debe tener 3 caracteres o más';

    public static function hasTwoDistinct(mixed $value): bool
    {
        $words = preg_split('/\s+/u', trim((string) $value)) ?: [];
        $unique = [];

        foreach ($words as $word) {
            $word = trim((string) $word);
            if (mb_strlen($word) < 3) {
                continue;
            }
            $unique[mb_strtolower($word)] = true;
        }

        return count($unique) >= 2;
    }

    public static function validationRule(): \Closure
    {
        return function () {
            return function (string $attribute, mixed $value, \Closure $fail): void {
                if (! self::hasTwoDistinct($value)) {
                    $fail(self::MESSAGE);
                }
            };
        };
    }
}
