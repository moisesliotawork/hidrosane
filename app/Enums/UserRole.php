<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case HEAD_OF_ROOM = 'head_of_room';
    case TELEOPERATOR = 'teleoperator';
    case COMMERCIAL = 'commercial';
    case GERENTE_GENERAL = 'gerente_general';
    case DELIVERY = 'delivery';
    case DELEGATE = 'delegate';
    case TEAM_LEADER = 'team_leader';
    case SALES_MANAGER = 'sales_manager';
    case APP_SUPPORT = 'app_support';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'ADMIN',
            self::HEAD_OF_ROOM => 'JEFE DE SALA',
            self::TELEOPERATOR => 'TELEOPERADOR',
            self::COMMERCIAL => 'COMERCIAL',
            self::GERENTE_GENERAL => 'GERENTE GENERAL',
            self::DELIVERY => 'REPARTIDOR',
            self::DELEGATE => 'DELEGADO',
            self::TEAM_LEADER => 'JEFE DE EQUIPO',
            self::SALES_MANAGER => 'JEFE DE VENTAS',
            self::APP_SUPPORT => 'SOPORTE',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::ADMIN, self::GERENTE_GENERAL => 'info',
            self::HEAD_OF_ROOM, self::TELEOPERATOR => 'pink',
            self::COMMERCIAL => 'success',
            self::DELIVERY => 'orange',
            default => 'gray',
        };
    }

    public static function options(): array
    {
        // Para Select::options()
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }
        return $out;
    }
}
