<?php

namespace App\Enums;

enum EstadoTerminal: string
{
    case SIN_ESTADO = '';
    case NUL = "nulo";
    case VENTA = 'venta';
    case CONFIRMADO = 'confirmado';
    case SALA = 'sala';

    public function label(): string
    {
        return match ($this) {
            self::SIN_ESTADO => 'S/E',
            self::NUL => 'NUL',
            self::VENTA => 'VTA',
            self::CONFIRMADO => 'CONF',
            self::SALA => 'OF',
        };
    }
}