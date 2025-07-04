<?php

namespace App\Enums;

enum NoteStatus: string
{
    case CONTACTED = 'contacted';
    case NULL = 'null';

    public function label(): string
    {
        return match ($this) {
            self::CONTACTED => 'Cont',
            self::NULL => 'Nul',
        };
    }

    public static function options(): array
    {
        return [
            self::CONTACTED->value => self::CONTACTED->label(),
            self::NULL->value => self::NULL->label(),
        ];
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CONTACTED => 'orange',
            self::NULL => 'info',
        };
    }
}