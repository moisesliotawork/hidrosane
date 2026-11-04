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
    /** @return array{rol: string, panel: string, etiqueta: string, email?: string, empleado_id?: array<int, string>}|null */
    public static function perfil(string $clave): ?array
    {
        $spec = config("demo.perfiles.{$clave}");

        return is_array($spec) ? $spec : null;
    }

    /**
     * Usuario de ese rol que pueda entrar al panel.
     *
     * Si el perfil trae número de empleado o email (los de Ohana), entra ESA
     * persona y no el primer registro del rol. Sin esos datos, se mantiene el
     * primer usuario del rol, para no romper la demo.
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
        $ids = array_values(array_filter(
            array_map(fn ($id) => trim((string) $id), (array) ($spec['empleado_id'] ?? [])),
            fn ($id) => $id !== ''
        ));
        $email = trim((string) ($spec['email'] ?? ''));

        $query = User::query()
            ->role($spec['rol'])
            ->where(fn ($q) => $q->whereNull('can_login')->orWhere('can_login', true));

        if ($ids !== [] || $email !== '') {
            $query->where(function ($q) use ($ids, $email) {
                if ($ids !== []) {
                    $q->whereIn('empleado_id', $ids);
                }
                if ($email !== '') {
                    $method = $ids === [] ? 'where' : 'orWhere';
                    $q->{$method}('email', $email);
                }
            });

            if ($ids !== []) {
                $marcas = implode(',', array_fill(0, count($ids), '?'));
                $query->orderByRaw("CASE WHEN empleado_id IN ({$marcas}) THEN 0 ELSE 1 END", $ids);
            }
        }

        return $query->orderBy('id')->first();
    }
}
