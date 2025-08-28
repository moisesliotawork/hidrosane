<?php
// app/Enums/SituacionLaboral.php
namespace App\Enums;

enum SituacionLaboral: string
{
    case PENSIONISTA = 'pensionista';
    case JUBILADO = 'jubilado';
    case EMPLEADO = 'empleado';
    case AUTONOMO = 'autonomo';
    case FUNCIONARIO = 'funcionario';

    case DESEMPLEADO = "desempleado";

    public function label(): string
    {
        return match ($this) {
            self::PENSIONISTA => 'Pensionista',
            self::JUBILADO => 'Jubilado/a',
            self::EMPLEADO => 'Empleado/a',
            self::AUTONOMO => 'Autónomo/a',
            self::FUNCIONARIO => 'Funcionario/a',
            self::DESEMPLEADO => 'Desempleado/a',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn($case) => [$case->value => $case->label()]
        )->toArray();
    }
}
