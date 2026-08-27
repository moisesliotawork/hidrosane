<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * Acceso a la identidad de marca definida en config/brand.php.
 */
class Brand
{
    public static function name(): string
    {
        return (string) config('brand.name', 'Ohana Plus');
    }

    /** URL pública del logo. */
    public static function logo(): string
    {
        return asset((string) config('brand.logo', 'images/logo.png'));
    }

    /**
     * Paleta primaria para Filament.
     *
     * @return array<int|string, string>
     */
    public static function primary(): array
    {
        $value = trim((string) config('brand.color', 'Lime'));

        if (str_starts_with($value, '#')) {
            return Color::hex($value);
        }

        $constant = Color::class.'::'.ucfirst(strtolower($value));

        return defined($constant) ? constant($constant) : Color::Lime;
    }
}
