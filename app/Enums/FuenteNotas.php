<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use App\Models\User;

enum FuenteNotas: string implements HasLabel, HasColor
{
    case CALLE = 'CALLE';
    case VIP_INT = 'VIP-INT';
    case VIP_EXT = 'VIP-EXT';
    case PTA_FRIA = 'PtaFria'; // NUEVO CASO PARA DIF CALLE DE PTA FRIA
    case EXCEL = 'excel';
    /** Solo visualización: Autogenerar Nota del comercial 911. No se guarda en notes.fuente. */
    case AG_N_ADMIN = 'AG_N/admin';
    /** Solo visualización: Puerta Fría real del comercial 911. No se guarda en notes.fuente. */
    case PF_ADMIN = 'PF-admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::CALLE => 'Calle',
            self::VIP_INT => 'VIP Interno',
            self::VIP_EXT => 'VIP Externo',
            self::PTA_FRIA => 'Puerta Fría',
            self::EXCEL => 'Excel',
            self::AG_N_ADMIN => 'AG_N/admin',
            self::PF_ADMIN => 'PF-admin',
        };
    }

    public function getPuntaje(): int
    {
        return match ($this) {
            self::CALLE => 4950,
            self::VIP_INT => 8900,
            self::VIP_EXT => 7500,
            self::PTA_FRIA => 0,
            self::EXCEL => 0,
            self::AG_N_ADMIN => 0,
            self::PF_ADMIN => 0,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CALLE => 'orange',
            self::VIP_INT => 'success',
            self::VIP_EXT => 'yellow',
            self::PTA_FRIA => 'danger', //
            self::EXCEL => 'info',
            self::AG_N_ADMIN => 'danger',
            self::PF_ADMIN => 'danger',
        };
    }

    // Método para obtener todas las opciones con sus propiedades
    public static function options(): array
    {
        return [
            self::CALLE->value => self::CALLE->getLabel(),
            self::VIP_INT->value => self::VIP_INT->getLabel(),
            self::VIP_EXT->value => self::VIP_EXT->getLabel(),
            self::PTA_FRIA->value => self::PTA_FRIA->getLabel(),
            self::EXCEL->value => self::EXCEL->getLabel(),
        ];
    }

    /** Opciones de tipo de nota según permisos del usuario. */
    public static function optionsForUser(?User $user): array
    {
        if ($user?->canSeeVipSources()) {
            return self::options();
        }

        return [
            self::CALLE->value => self::CALLE->getLabel(),
        ];
    }
}
