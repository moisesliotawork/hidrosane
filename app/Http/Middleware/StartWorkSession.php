<?php
namespace App\Http\Middleware;

use App\Models\User;
use App\Models\WorkSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stevebauman\Location\Facades\Location;
use Filament\Facades\Filament;

class StartWorkSession
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // 👇 A veces en el primer hit Filament aún no resolvió el panel: evita nulls inconsistentes
            $panelId = Filament::getCurrentPanel()?->getId() ?? 'admin'; // o el slug que prefieras por defecto

            $activeSessionExists = WorkSession::where('user_id', $user->id)
                ->where('panel_id', $panelId)
                ->active()
                ->exists();

            if (!$activeSessionExists) {
                DB::transaction(function () use ($request, $user, $panelId) {
                    $ip = $request->ip();

                    // 👇 Aísla geolocalización para que NUNCA tumbe la transacción
                    try {
                        $location = Location::get($ip);
                    } catch (\Throwable $e) {
                        $location = false;
                    }

                    $defaultLocation = (object) ['latitude' => 10.4806, 'longitude' => -66.9036];
                    $loc = ($location === false || in_array($ip, ['127.0.0.1', '::1']))
                        ? $defaultLocation
                        : $location;

                    WorkSession::create([
                        'user_id' => $user->id,
                        'panel_id' => $panelId,
                        'start_time' => now(),
                        'latitude' => $loc->latitude,
                        'longitude' => $loc->longitude,
                        'ip_address' => $ip,
                        'device_info' => $request->userAgent(),
                    ]);

                    // 👇 Evita issues de modelo cacheado; actualiza directo en DB
                    User::whereKey($user->id)->update(['is_active' => true]);

                    // 👇 Si luego lo necesitas en memoria:
                    $user->refresh();
                });
            }
        }

        return $next($request);
    }
}
