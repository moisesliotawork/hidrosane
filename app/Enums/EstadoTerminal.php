<?php

namespace App\Enums;

enum EstadoTerminal: string
{
    case SIN_ESTADO = '';
    case NUL = "nulo";
    case VENTA = 'venta';
    case CONFIRMADO = 'confirmado';
    case SALA = 'sala';
    case AUSENTE = 'ausente';

    public function label(): string
    {
        return match ($this) {
            self::SIN_ESTADO => 'S/E',
            self::NUL => 'NUL',
            self::VENTA => 'VTA',
            self::CONFIRMADO => 'CONF',
            self::SALA => 'OF',
            self::AUSENTE => 'AUS',
        };
    }

    /** Etiqueta legible para la columna «En nota» (HeadOfRoom). */
    public function enNotaLabel(): string
    {
        return match ($this) {
            self::SIN_ESTADO => 'Sin estado',
            self::NUL => 'Nula',
            self::VENTA => 'Venta',
            self::CONFIRMADO => 'Confirmada',
            self::SALA => 'Oficina',
            self::AUSENTE => 'Ausente',
        };
    }

    public function enNotaColor(): string
    {
        return match ($this) {
            self::NUL => 'danger',
            self::VENTA => 'success',
            self::CONFIRMADO => 'warning',
            self::SALA => 'pink',
            self::AUSENTE => 'info',
            self::SIN_ESTADO => 'gray',
        };
    }

    /** Siguiente estado al hacer clic cíclico en la tabla. */
    public static function nextFromRaw(?string $raw): self
    {
        $cycle = [
            '' => self::NUL,
            self::NUL->value => self::VENTA,
            self::VENTA->value => self::CONFIRMADO,
            self::CONFIRMADO->value => self::SALA,
            self::SALA->value => self::AUSENTE,
            self::AUSENTE->value => self::SIN_ESTADO,
        ];

        return $cycle[$raw ?? ''] ?? self::NUL;
    }
}