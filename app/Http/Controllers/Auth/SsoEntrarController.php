<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccesoDirecto;
use App\Support\SsoToken;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Entrada desde Ohana con token firmado.
 *
 * Inerte mientras SSO_SECRET esté vacío: la ruta responde 404, así que quitar
 * la variable cierra la puerta sin tocar código ni desplegar otra rama.
 */
class SsoEntrarController extends Controller
{
    public function __invoke(Request $request)
    {
        $secreto = (string) config('sso.secret', '');

        abort_if($secreto === '', 404);

        $carga = SsoToken::verificar((string) $request->query('token', ''), $secreto);

        abort_unless($carga, 403, 'Token inválido o caducado.');

        // Un solo uso. Cache::add es atómico: devuelve false si la clave ya
        // existía, así que dos peticiones con el mismo token no pueden colarse
        // las dos ni aunque lleguen a la vez.
        $nuevo = Cache::add('sso:nonce:'.$carga['nonce'], true, (int) config('sso.ttl', 60));

        abort_unless($nuevo, 403, 'Este enlace ya se usó.');

        $spec = AccesoDirecto::perfil((string) $carga['perfil']);

        abort_unless($spec, 404);

        $user = AccesoDirecto::usuario($spec);

        abort_unless($user, 404, "No hay ningún usuario con el rol {$spec['rol']}.");

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Filament::getPanel($spec['panel'])->getUrl());
    }
}
