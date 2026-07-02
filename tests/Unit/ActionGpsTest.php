<?php

namespace Tests\Unit;

use App\Support\ActionGps;
use Tests\TestCase;

class ActionGpsTest extends TestCase
{
    public function test_accepts_galicia_coordinates(): void
    {
        $coords = ActionGps::validateOperatingCoords('42.240598', '-8.720726');

        $this->assertNotNull($coords);
        $this->assertSame('42.240598', $coords['lat']);
        $this->assertSame('-8.720726', $coords['lng']);
    }

    public function test_accepts_madrid_coordinates(): void
    {
        $this->assertNotNull(ActionGps::validateOperatingCoords('40.4168', '-3.7038'));
    }

    public function test_accepts_canary_islands_coordinates(): void
    {
        $this->assertNotNull(ActionGps::validateOperatingCoords('28.1235', '-15.4363'));
    }

    public function test_rejects_legacy_caracas_fallback_coordinates(): void
    {
        $this->assertNull(ActionGps::validateOperatingCoords('10.4806', '-66.9036'));
    }

    public function test_rejects_coordinates_outside_spain(): void
    {
        $this->assertNull(ActionGps::validateOperatingCoords('48.8566', '2.3522'));
    }

    public function test_resolve_ignores_invalid_coordinates_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $coords = ActionGps::resolve([
            'gps_lat' => '10.4806',
            'gps_lng' => '-66.9036',
        ]);

        $this->assertNull($coords['lat']);
        $this->assertNull($coords['lng']);
    }
}
