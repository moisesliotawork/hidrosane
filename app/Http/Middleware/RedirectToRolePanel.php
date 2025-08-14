<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToRolePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Solo actuamos si ya está autenticado
        if ($user) {
            $target = match (true) {
                $user->hasRole('admin')           => '/admin',
                $user->hasRole('commercial')      => '/comercial',
                $user->hasRole('teleoperator')    => '/teleoperador',
                $user->hasRole('head_of_room')    => '/jefe-sala',
                $user->hasRole('gerente_general') => '/gerente',
                $user->hasRole('delivery')        => '/repartidor',
                $user->hasRole('app_support')        => '/superAdmin',
                default                           => '/',            // fallback
            };

            // Si ya estamos en su panel, seguimos; si no, redirigimos
            if (! $request->is(ltrim($target, '/').'*')) {
                return redirect()->to($target);
            }
        }

        return $next($request);
    }
}
