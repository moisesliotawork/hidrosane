<?php

namespace App\Filament\Widgets;

use App\Support\AccesoDirecto;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Tarjeta en el escritorio con la vuelta a Ohana.
 *
 * Solo aparece si hay secreto configurado y si el usuario tiene alguno de los
 * tres roles del mapa.
 */
class OhanaAccesoWidget extends Widget
{
    protected static string $view = 'filament.widgets.ohana-acceso';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return blank(config('sso.secret')) === false && count(self::perfiles()) > 0;
    }

    /** @return array<string, array{rol: string, etiqueta: string}> */
    public static function perfiles(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $permitidos = [];

        foreach (['admin', 'superadmin', 'gerente'] as $clave) {
            $spec = AccesoDirecto::perfil($clave);

            if ($spec && $user->hasRole($spec['rol'])) {
                $permitidos[$clave] = $spec;
            }
        }

        return $permitidos;
    }
}
