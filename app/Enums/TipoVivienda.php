<?php
// app/Enums/TipoVivienda.php
namespace App\Enums;

enum TipoVivienda: string
{
    case PROPIA     = 'propia';
    case ALQUILER   = 'alquiler';
    case FAMILIAR   = 'familiar';
    case OTRA       = 'otra';

    /* Texto legible */
    public function label(): string
    {
        return match ($this) {
            self::PROPIA   => 'Propia',
            self::ALQUILER => 'Alquiler',
            self::FAMILIAR => 'Familiar',
            self::OTRA     => 'Otra',
        };
    }

    /* Para Select::options() */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn($case) => [$case->value => $case->label()]
        )->toArray();
    }
}
