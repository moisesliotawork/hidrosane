<?php
// app/Enums/EstadoCivil.php
namespace App\Enums;

enum EstadoCivil: string
{
    case SOLTERO = 'soltero';
    case CASADO = 'casado';
    case SEPARADO = 'separado';
    case DIVORCIADO = 'divorciado';
    case VIUDO = 'viudo';

    public function label(): string
    {
        return match ($this) {
            self::SOLTERO => 'Soltero/a',
            self::CASADO => 'Casado/a',
            self::SEPARADO => 'Separado/a',
            self::DIVORCIADO => 'Divorciado/a',
            self::VIUDO => 'Viudo/a',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn($case) => [$case->value => $case->label()]
        )->toArray();
    }
}
