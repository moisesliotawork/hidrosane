<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Búsqueda de clientes por nombre tolerante a abreviaciones (Mª → maria)
 * y acentos (Pérez ↔ perez, Calviño ↔ calvino).
 *
 * Si el término tiene varias palabras, basta con que coincida cualquiera
 * (p. ej. apellido correcto aunque el nombre esté mal).
 */
final class CustomerNameSearch
{
    /**
     * Tokens normalizados del término de búsqueda (min. 2 caracteres).
     *
     * @return list<string>
     */
    public static function tokens(string $search): array
    {
        $normalized = self::normalize($search);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 2) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        // Abreviaciones comunes de María / Mario antes de ascii
        $value = str_replace(
            ['mª', 'm.ª', 'mº', 'm.º'],
            ['maria', 'maria', 'mario', 'mario'],
            $value
        );
        $value = Str::ascii($value);
        $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        // Tras ascii, "Mª" suele quedar "ma"
        $value = preg_replace('/\bma\b/u', 'maria', $value) ?? $value;

        return trim($value);
    }

    /**
     * Expresión SQL del nombre completo normalizado (lowercase + Mª→maria + acentos/ñ).
     */
    public static function normalizedFullNameSql(string $firstCol = 'first_names', string $lastCol = 'last_names'): string
    {
        $expr = "LOWER(CONCAT(COALESCE({$firstCol},''),' ',COALESCE({$lastCol},'')))";

        foreach ([
            'mª' => 'maria',
            'm.ª' => 'maria',
            'mº' => 'mario',
            'm.º' => 'mario',
            'ñ' => 'n',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ] as $from => $to) {
            $expr = 'REPLACE('.$expr.', '.self::sqlString($from).', '.self::sqlString($to).')';
        }

        return $expr;
    }

    /**
     * Restringe un query de Customer (o tabla customers) por nombre.
     * Cualquier token que coincida basta (p. ej. apellido correcto aunque el nombre falle).
     *
     * @param  Builder<\App\Models\Customer>  $query
     * @return Builder<\App\Models\Customer>
     */
    public static function constrainCustomer(Builder $query, string $search): Builder
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return $query->whereRaw('1 = 0');
        }

        $expr = self::normalizedFullNameSql('first_names', 'last_names');
        $firstExpr = self::normalizedColumnSql('first_names');
        $lastExpr = self::normalizedColumnSql('last_names');

        return $query->where(function (Builder $q) use ($tokens, $expr, $firstExpr, $lastExpr): void {
            foreach ($tokens as $token) {
                $like = '%'.$token.'%';
                $q->orWhere(function (Builder $tokenQuery) use ($like, $expr, $firstExpr, $lastExpr): void {
                    $tokenQuery->whereRaw("{$expr} LIKE ?", [$like])
                        ->orWhereRaw("{$firstExpr} LIKE ?", [$like])
                        ->orWhereRaw("{$lastExpr} LIKE ?", [$like]);
                });
            }
        });
    }

    /**
     * Restringe ventas/notas (u otro modelo con relación customer) por nombre del cliente.
     * Cualquier token que coincida basta (apellido correcto aunque el nombre falle).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrainHasCustomer(Builder $query, string $search, string $relation = 'customer'): Builder
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($relation, function (Builder $q) use ($tokens): void {
            self::constrainCustomer($q, implode(' ', $tokens));
        });
    }

    /** Expresión SQL de una columna de nombre normalizada (lowercase + Mª→maria + acentos/ñ). */
    public static function normalizedColumnSql(string $column): string
    {
        $expr = "LOWER(COALESCE({$column},''))";

        foreach ([
            'mª' => 'maria',
            'm.ª' => 'maria',
            'mº' => 'mario',
            'm.º' => 'mario',
            'ñ' => 'n',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ] as $from => $to) {
            $expr = 'REPLACE('.$expr.', '.self::sqlString($from).', '.self::sqlString($to).')';
        }

        return $expr;
    }

    private static function sqlString(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
