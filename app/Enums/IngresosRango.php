<?php
// app/Enums/IngresosRango.php
namespace App\Enums;

enum IngresosRango: string
{
    case MENOS_500 = '<500';
    case ENTRE_500_600 = '500-600';
    case ENTRE_600_900 = '600-900';
    case ENTRE_900_1200 = '900-1200';
    case MAS_1200 = '>1200';

    public function label(): string
    {
        return match ($this) {
            self::MENOS_500 => '< 500 €',
            self::ENTRE_500_600 => '500 – 600 €',
            self::ENTRE_600_900 => '600 – 900 €',
            self::ENTRE_900_1200 => '900 – 1 200 €',
            self::MAS_1200 => '> 1 200 €',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn($case) => [$case->value => $case->label()]
        )->toArray();
    }
}
