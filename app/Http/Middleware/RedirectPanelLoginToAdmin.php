<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPanelLoginToAdmin
{
    /** Rutas de login que queremos “re-encaminar” */
    protected array $panelLoginPatterns = [
        'comercial/login',
        'teleoperador/login',
        'jefe-sala/login',
        'gerente/login',
        'repartidor/login',
        'superAdmin/login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Si la URL coincide con alguno de los formularios de login de los paneles
        // NO-admin, lo mandamos al login central.
        if ($request->is($this->panelLoginPatterns)) {
            return redirect('/admin/login');
        }

        return $next($request);
    }
}
