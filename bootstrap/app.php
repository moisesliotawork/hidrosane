<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // La app no tiene una ruta 'login' global: cada panel de Filament (admin,
        // superAdmin, comercial, jefe-sala, ...) tiene su propio login. Las rutas
        // sueltas de routes/web.php que usan el middleware 'auth' a secas (los PDF
        // de superadmin, cream-transfers, etc.) intentaban redirigir a route('login')
        // cuando la sesión caducaba, y como esa ruta no existe, eso provocaba un 500
        // en vez de mandar al usuario de vuelta al login correspondiente.
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request): string {
            $path = $request->path();

            return match (true) {
                str_starts_with($path, 'superadmin') => '/superAdmin/login',
                str_starts_with($path, 'comercial') => '/comercial/login',
                str_starts_with($path, 'admin') => '/admin/login',
                str_starts_with($path, 'head-of-room') => '/jefe-sala/login',
                default => '/admin/login',
            };
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
