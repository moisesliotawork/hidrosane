<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolución del usuario detrás de un perfil de acceso directo.
 *
 * Lo comparten los botones de demostración (DemoLoginController) y la entrada
 * desde Ohana (SsoEntrarController): el mapa de perfiles vive en config/demo.php
 * y es el mismo para los dos.
 */
final class AccesoDirecto
{
    /** @return array{rol: string, panel: string, etiqueta: string}|null */
    public static function perfil(string $clave): ?array
    {
        $spec = config("demo.perfiles.{$clave}");

        return is_array($spec) ? $spec : null;
    }

    /**
     * Primer usuario activo con ese rol que además pueda entrar.
     *
     * can_login es nullable y el modelo trata null como permitido
     * (User::canAccessPanel), así que aquí se replica ese criterio.
     */
    public static function usuario(array $spec): ?User
    {
        return User::query()
            ->role($spec['rol'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('can_login')->orWhere('can_login', true))
            ->orderBy('id')
            ->first();
    }
}
