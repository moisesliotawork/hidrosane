<?php

namespace App\Http\Controllers;

use App\Support\AccesoDirecto;
use App\Support\SsoToken;
use Illuminate\Http\Request;

/**
 * Salida hacia Ohana: firma un token de un solo uso y redirige.
 *
 * La autorización se decide AQUÍ, con el rol que el usuario tiene en Hidrosane.
 * Ohana solo comprueba la firma y el destinatario.
 */
class IrAOhanaController extends Controller
{
    public function __invoke(Request $request, string $perfil)
    {
        $secreto = (string) config('sso.secret', '');

        abort_if($secreto === '', 404);

        $spec = AccesoDirecto::perfil($perfil);

        abort_unless($spec, 404);

        $user = $request->user();

        abort_unless($user && $user->hasRole($spec['rol']), 403);

        $token = SsoToken::firmar([
            'aud' => 'ohana',
            'perfil' => $perfil,
            'exp' => time() + (int) config('sso.ttl', 60),
            'nonce' => SsoToken::nonce(),
        ], $secreto);

        $destino = rtrim((string) config('ohana.url'), '/')
            .'/sso/entrar?token='.urlencode($token);

        return redirect()->away($destino);
    }
}
