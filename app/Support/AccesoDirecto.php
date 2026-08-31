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
     * Primer usuario con ese rol que pueda entrar al panel.
     *
     * OJO CON is_active: en esta app NO significa «cuenta habilitada», significa
     * «tiene un fichaje abierto». Lo pone a true el middleware StartWorkSession
     * y a false LogoutController. Filtrar por él hacía que el acceso directo
     * dejara de funcionar en cuanto el usuario de destino cerraba sesión.
     *
     * El criterio bueno es can_login, que es el que mira User::canAccessPanel.
     * Es nullable y null cuenta como permitido, así que se replica igual.
     */
    public static function usuario(array $spec): ?User
    {
        return User::query()
            ->role($spec['rol'])
            ->where(fn ($q) => $q->whereNull('can_login')->orWhere('can_login', true))
            ->orderBy('id')
            ->first();
    }
}
