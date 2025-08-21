<?php

namespace App\Enums;

enum Financiera: string
{
    case CREDIBOX = 'CREDIBOX';
    case FINDIRECT = 'findirect';
    case MONTJUIT = 'MONTJUIT';

    // (Opcional) Helpers para UI (badges, labels, etc.)
    public function label(): string
    {
        return match ($this) {
            self::CREDIBOX => 'CREDIBOX',
            self::FINDIRECT => 'findirect',
            self::MONTJUIT => 'MONTJUIT',
        };
    }

    public function color(): ?string
    {
        // Útil si usas Filament badges
        return match ($this) {
            self::CREDIBOX => 'success',
            self::FINDIRECT => 'warning',
            self::MONTJUIT => 'info',
        };
    }
}
