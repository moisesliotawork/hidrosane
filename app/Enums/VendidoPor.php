<?php

namespace App\Enums;

enum VendidoPor: string
{
    case Comercial   = 'comercial';
    case Repartidor  = 'repartidor';

    public static function options(): array
    {
        return [
            self::Comercial->value => 'Comercial',
            self::Repartidor->value => 'Repartidor',
        ];
    }
}

