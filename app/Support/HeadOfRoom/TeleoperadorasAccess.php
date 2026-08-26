<?php

namespace App\Support\HeadOfRoom;

/**
 * Acceso con clave al recurso Teleoperadoras (panel Jefe de Sala).
 * Una vez desbloqueado, permanece abierto durante la sesión del navegador.
 */
final class TeleoperadorasAccess
{
    public const PIN = '1079';

    public const SESSION_KEY = 'head_of_room.teleoperadoras_unlocked';

    public static function isUnlocked(): bool
    {
        return (bool) session(self::SESSION_KEY, false);
    }

    public static function unlock(): void
    {
        session([self::SESSION_KEY => true]);
    }

    public static function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
