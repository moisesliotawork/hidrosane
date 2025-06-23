<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FuenteNotas: string implements HasLabel, HasColor
{
    case CALLE = 'CALLE';
    case VIP_INT = 'VIP-INT';
    case VIP_EXT = 'VIP-EXT';

    public function getLabel(): string
    {
        return match ($this) {
            self::CALLE => 'Calle',
            self::VIP_INT => 'VIP Interno',
            self::VIP_EXT => 'VIP Externo',
        };
    }

    public function getPuntaje(): int
    {
        return match ($this) {
            self::CALLE => 1000,
            self::VIP_INT => 2000,
            self::VIP_EXT => 3000,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CALLE => 'orange',
            self::VIP_INT => 'success',
            self::VIP_EXT => 'primary',
        };
    }

    // Método para obtener todas las opciones con sus propiedades
    public static function options(): array
    {
        return [
            self::CALLE->value => self::CALLE->getLabel(),
            self::VIP_INT->value => self::VIP_INT->getLabel(),
            self::VIP_EXT->value => self::VIP_EXT->getLabel(),
        ];
    }
}