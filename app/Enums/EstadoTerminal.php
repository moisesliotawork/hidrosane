<?php

namespace App\Enums;

enum EstadoTerminal: string
{
    case SIN_ESTADO = '';
    case NULO = "nulo";
    case VENTA = 'venta';
    case CONFIRMADO = 'confirmado';
    case SALA = 'sala';

    public function label(): string
    {
        return match ($this) {
            self::SIN_ESTADO => 'S/E',
            self::NULO => 'NUL',
            self::VENTA => 'VTA',
            self::CONFIRMADO => 'CONF',
            self::SALA => 'OF',
        };
    }
}