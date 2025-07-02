<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum HorarioNotas: string implements HasLabel
{
    case _11_13 = '11-13';
    case _12_14 = '12-14';
    case _13_1430 = '13-14:30';
    case TM = 'TM';
    case _1530_17 = '15:30-17';
    case _16_18 = '16-18';
    case _17_19 = '17-19';
    case _18_20 = '18-20';
    case TT = 'TT';
    case TD = 'TD';

    public function getLabel(): string
    {
        return match ($this) {
            self::_11_13 => '11-13',
            self::_12_14 => '12-14',
            self::_13_1430 => '13-14:30',
            self::TM => 'TM',
            self::_1530_17 => '15:30-17',
            self::_16_18 => '16-18',
            self::_17_19 => '17-19',
            self::_18_20 => '18-20',
            self::TT => 'TT',
            self::TD => 'TD',
        };
    }

    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $options, self $case) => $options + [$case->value => $case->getLabel()],
            []
        );
    }
}