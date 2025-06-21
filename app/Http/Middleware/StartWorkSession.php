<?php
namespace App\Http\Middleware;

use App\Models\WorkSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stevebauman\Location\Facades\Location;
use Filament\Facades\Filament;

class StartWorkSession
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $panelId = Filament::getCurrentPanel()?->getId();

            $activeSession = WorkSession::where('user_id', $user->id)
                ->where('panel_id', $panelId)
                ->active()
                ->exists();

            if (!$activeSession) {
                $ip = $request->ip();
                $location = Location::get($ip);

                // Coordenadas de Caracas, Venezuela por defecto para IPs locales
                $defaultLocation = (object) [
                    'latitude' => 10.4806,
                    'longitude' => -66.9036,
                ];

                $location = ($location === false || in_array($ip, ['127.0.0.1', '::1']))
                    ? $defaultLocation
                    : $location;

                WorkSession::create([
                    'user_id' => $user->id,
                    'panel_id' => $panelId, // Guardamos el panel actual
                    'start_time' => now(),
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'ip_address' => $ip,
                    'device_info' => $request->userAgent(),
                ]);
            }
        }

        return $next($request);
    }
}
