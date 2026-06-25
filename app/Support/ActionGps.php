<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

class ActionGps
{
    /**
     * Resuelve lat/lng desde el formulario de una acción Filament (Confirmada, Ausente, Nulo…).
     * Trata '' como vacío: el hidden gps_lat suele llegar en blanco si el GPS aún no respondió.
     */
    public static function resolve(array $data): array
    {
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

    public static function resolveFromCoords(?string $lat, ?string $lng): array
    {
        return self::resolve([
            'gps_lat' => $lat,
            'gps_lng' => $lng,
        ]);
    }
}
