<?php

namespace App\Enums;

enum EstadoEntrega: string
{
    case NO_ENTREGADO = 'no_entregado';
    case PARCIAL = 'parcial';
    case COMPLETO = 'completo';

    /** Nombre legible para tablas, badges, etc. */
    public function label(): string
    {
        return match ($this) {
            self::NO_ENTREGADO => 'No entregado',
            self::PARCIAL => 'Parcial',
            self::COMPLETO => 'Completo',
        };
    }

    /** Array utilitario → ['no_entregado' => 'No entregado', …] */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($e) => [$e->value => $e->label()])
            ->all();
    }

    /** → ['no_entregado', 'parcial', 'completo'] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
