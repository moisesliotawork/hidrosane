<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada sin credenciales a un panel, para la instancia de demostración.
 *
 * Inerte mientras demo.login sea false: la ruta responde 404, de modo que
 * apagar la variable basta para cerrar el acceso sin tocar código.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request, string $perfil)
    {
        abort_unless(config('demo.login'), 404);

        $spec = config("demo.perfiles.{$perfil}");

        abort_unless(is_array($spec), 404);

        $user = User::query()
            ->role($spec['rol'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('can_login')->orWhere('can_login', true))
            ->orderBy('id')
            ->first();

        abort_unless($user, 404, "No hay ningún usuario con el rol {$spec['rol']}.");

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Filament::getPanel($spec['panel'])->getUrl());
    }
}
