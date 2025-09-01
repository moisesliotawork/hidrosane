<?php

namespace App\Enums;

enum MesesEnum: string
{
    case ENERO = 'enero';
    case FEBRERO = 'febrero';
    case MARZO = 'marzo';
    case ABRIL = 'abril';
    case MAYO = 'mayo';
    case JUNIO = 'junio';
    case JULIO = 'julio';
    case AGOSTO = 'agosto';
    case SEPTIEMBRE = 'septiembre';
    case OCTUBRE = 'octubre';
    case NOVIEMBRE = 'noviembre';
    case DICIEMBRE = 'diciembre';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public static function fromMonthNumber(int $month): self
    {
        return match ($month) {
            1 => self::ENERO, 2 => self::FEBRERO, 3 => self::MARZO, 4 => self::ABRIL,
            5 => self::MAYO, 6 => self::JUNIO, 7 => self::JULIO, 8 => self::AGOSTO,
            9 => self::SEPTIEMBRE, 10 => self::OCTUBRE, 11 => self::NOVIEMBRE, 12 => self::DICIEMBRE,
            default => throw new \InvalidArgumentException('Mes inválido: ' . $month),
        };
    }
}
