<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            DB::transaction(function () use ($user) {
                // Cierra TODAS las sesiones activas del usuario (en cualquier panel)
                WorkSession::where('user_id', $user->id)
                    ->whereNull('end_time')
                    ->update(['end_time' => now()]);

                // Marca al usuario como inactivo
                User::whereKey($user->id)->update(['is_active' => false]);
            });
        }

        // Logout estándar
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirige al login central (ajusta si quieres ir a otro panel)
        return redirect('/admin/login');
    }
}
