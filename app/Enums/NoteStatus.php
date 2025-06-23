<?php

namespace App\Enums;

enum NoteStatus: string
{
    case CONTACTED = 'contacted';
    case RESCHEDULED = 'rescheduled';
    case NULL = 'null';

    public function label(): string
    {
        return match ($this) {
            self::CONTACTED => 'Contactado',
            self::RESCHEDULED => 'Reprogramado',
            self::NULL => 'Nulo',
        };
    }

    public static function options(): array
    {
        return [
            self::CONTACTED->value => self::CONTACTED->label(),
            self::RESCHEDULED->value => self::RESCHEDULED->label(),
            self::NULL->value => self::NULL->label(),
        ];
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CONTACTED => 'info',
            self::RESCHEDULED => 'info',
            self::NULL => 'info',
        };
    }
}