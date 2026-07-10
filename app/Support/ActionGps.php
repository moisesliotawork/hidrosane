<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\App;

class ActionGps
{
    /** Territorio español aproximado: península, Baleares, Canarias, Ceuta y Melilla. */
    public const OPERATING_LAT_MIN = 27.5;

    public const OPERATING_LAT_MAX = 43.9;

    public const OPERATING_LNG_MIN = -18.5;

    public const OPERATING_LNG_MAX = 4.6;

    /** Coordenadas de fallback erróneas usadas en versiones anteriores (Caracas). */
    public const LEGACY_INVALID_LAT = 10.4806;

    public const LEGACY_INVALID_LNG = -66.9036;

    public const GPS_EXEMPT_COMMERCIAL_EMPLEADO_ID = '911';

    public const GPS_EXEMPT_COMMERCIAL_EMAIL = 'contratos@gmail.com';

    /**
     * Comercial único exento de GPS al declarar (emergencia / contratos).
     */
    public static function isGpsExempt(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        $empleadoId = trim((string) ($user->empleado_id ?? ''));
        $email = strtolower(trim((string) ($user->email ?? '')));

        return $empleadoId === self::GPS_EXEMPT_COMMERCIAL_EMPLEADO_ID
            && $email === self::GPS_EXEMPT_COMMERCIAL_EMAIL
            && $user->hasRole('commercial');
    }

    /**
     * Solo comercial y jefe de equipo registran GPS al declarar.
     * Gerente (rol o empleado_id 001) y el comercial 911/contratos@gmail.com nunca guardan ubicación.
     */
    public static function shouldRegisterGps(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (self::isGpsExempt($user)) {
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

    public static function isPlausibleOperatingCoordinate(float $lat, float $lng): bool
    {
        if (
            abs($lat - self::LEGACY_INVALID_LAT) < 0.05
            && abs($lng - self::LEGACY_INVALID_LNG) < 0.05
        ) {
            return false;
        }

        return $lat >= self::OPERATING_LAT_MIN
            && $lat <= self::OPERATING_LAT_MAX
            && $lng >= self::OPERATING_LNG_MIN
            && $lng <= self::OPERATING_LNG_MAX;
    }

    /**
     * @return array{lat: string, lng: string}|null
     */
    public static function validateOperatingCoords(mixed $lat, mixed $lng): ?array
    {
        if (! filled($lat) || ! filled($lng)) {
            return null;
        }

        $latFloat = (float) $lat;
        $lngFloat = (float) $lng;

        if (! self::isPlausibleOperatingCoordinate($latFloat, $lngFloat)) {
            return null;
        }

        return [
            'lat' => trim((string) $lat),
            'lng' => trim((string) $lng),
        ];
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

        $validated = self::validateOperatingCoords($lat, $lng);

        if ($validated !== null) {
            return $validated;
        }

        if (App::environment('local')) {
            return ['lat' => '42.2405', 'lng' => '-8.7200'];
        }

        return ['lat' => null, 'lng' => null];
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
