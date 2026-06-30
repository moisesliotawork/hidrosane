<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\App;

class ActionGps
{
    /**
     * Solo comercial y jefe de equipo registran GPS al declarar.
     * Gerente (rol o empleado_id 001) nunca guarda ubicación.
     */
    public static function shouldRegisterGps(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('gerente')) {
            return false;
        }

        if (trim((string) ($user->empleado_id ?? '')) === '001') {
            return false;
        }

        return $user->hasAnyRole(['commercial', 'team_leader']);
    }

    /**
     * Resuelve lat/lng desde el formulario de una acción Filament (Confirmada, Ausente, Nulo…).
     * Trata '' como vacío: el hidden gps_lat suele llegar en blanco si el GPS aún no respondió.
     */
    public static function resolve(array $data, ?User $user = null): array
    {
        if (! self::shouldRegisterGps($user)) {
            return ['lat' => null, 'lng' => null];
        }

        $lat = filled($data['gps_lat'] ?? null) ? $data['gps_lat'] : null;
        $lng = filled($data['gps_lng'] ?? null) ? $data['gps_lng'] : null;

        if (filled($lat) && filled($lng)) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        if (App::environment('local')) {
            return ['lat' => '42.2405', 'lng' => '-8.7200'];
        }

        return [
            'lat' => filled($lat) ? $lat : request()->input('latitud'),
            'lng' => filled($lng) ? $lng : request()->input('longitud'),
        ];
    }

    public static function resolveFromCoords(?string $lat, ?string $lng, ?User $user = null): array
    {
        return self::resolve([
            'gps_lat' => $lat,
            'gps_lng' => $lng,
        ], $user);
    }

    /** @return array{lat: ?string, lng: ?string} */
    public static function coordsForVenta(?string $noteLat, ?string $noteLng, array $data = [], ?User $user = null): array
    {
        if (! self::shouldRegisterGps($user)) {
            return ['lat' => null, 'lng' => null];
        }

        $captured = self::resolve($data, $user);

        if (filled($captured['lat']) && filled($captured['lng'])) {
            return $captured;
        }

        return [
            'lat' => filled($noteLat) ? $noteLat : null,
            'lng' => filled($noteLng) ? $noteLng : null,
        ];
    }
}
