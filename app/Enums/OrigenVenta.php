<?php

namespace App\Enums;

enum OrigenVenta: string
{
    case PUERTA_FRIA = 'puerta_fria';
    case VENTA_NORMAL = 'venta_normal';
    case EXCEL = 'excel';

    public function label(): string
    {
        return match ($this) {
            self::PUERTA_FRIA => 'Puerta fría',
            self::VENTA_NORMAL => 'Venta normal',
            self::EXCEL => 'Excel',
        };
    }

    public static function options(): array
    {
        return [
            self::PUERTA_FRIA->value => self::PUERTA_FRIA->label(),
            self::VENTA_NORMAL->value => self::VENTA_NORMAL->label(),
            self::EXCEL->value => self::EXCEL->label(),
        ];
    }
}