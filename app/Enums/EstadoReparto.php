<?php
// app/Enums/EstadoReparto.php

namespace App\Enums;

enum EstadoReparto: string
{
    case PENDIENTE = 'pendiente';
    case NULO_REPARTO = 'nulo_reparto';
    case NULO_FINANCIERO = 'nulo_financiero';
    case NULO_AUSENTE = 'nulo_ausente';
    case ENTREGA_SIMPLE = 'entrega_simple';
    case ENTREGA_VENTA = 'entrega_venta';

    /** Texto legible para cada estado */
    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::NULO_REPARTO => 'Nulo en Reparto',
            self::NULO_FINANCIERO => 'Nulo Financiero',
            self::NULO_AUSENTE => 'Nulo por Ausente',
            self::ENTREGA_SIMPLE => 'Entrega Simple',
            self::ENTREGA_VENTA => 'Entrega con Venta',
        };
    }

    /** Color para Filament u otras vistas */
    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE => 'warning',
            self::NULO_REPARTO => 'gray',
            self::NULO_FINANCIERO => 'danger',
            self::NULO_AUSENTE => 'info',
            self::ENTREGA_SIMPLE => 'success',
            self::ENTREGA_VENTA => 'success',
        };
    }
}
