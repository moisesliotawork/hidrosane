<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ActionGps;
use Mockery;
use Tests\TestCase;

class ActionGpsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function mockCommercialUser(string $empleadoId, string $email): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->empleado_id = $empleadoId;
        $user->email = $email;
        $user->shouldReceive('hasRole')->with('gerente')->andReturn(false);
        $user->shouldReceive('hasRole')->with('commercial')->andReturn(true);
        $user->shouldReceive('hasAnyRole')->with(['commercial', 'team_leader'])->andReturn(true);

        return $user;
    }

    public function test_commercial_911_contratos_is_gps_exempt(): void
    {
        $user = $this->mockCommercialUser('911', 'contratos@gmail.com');

        $this->assertTrue(ActionGps::isGpsExempt($user));
        $this->assertFalse(ActionGps::shouldRegisterGps($user));
        $this->assertSame(['lat' => null, 'lng' => null], ActionGps::resolve([
            'gps_lat' => '42.240598',
            'gps_lng' => '-8.720726',
        ], $user));
    }

    public function test_other_commercial_still_requires_gps(): void
    {
        $user = $this->mockCommercialUser('912', 'contratos@gmail.com');

        $this->assertFalse(ActionGps::isGpsExempt($user));
        $this->assertTrue(ActionGps::shouldRegisterGps($user));
    }

    public function test_commercial_911_with_other_email_still_requires_gps(): void
    {
        $user = $this->mockCommercialUser('911', 'otro@gmail.com');

        $this->assertFalse(ActionGps::isGpsExempt($user));
        $this->assertTrue(ActionGps::shouldRegisterGps($user));
    }

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

    public function test_assert_coords_for_venta_uses_note_fallback_when_wizard_empty(): void
    {
        $user = $this->mockCommercialUser('100', 'comercial@test.com');

        $coords = ActionGps::assertCoordsForVentaOrFail(
            '42.2405',
            '-8.7200',
            [],
            $user,
        );

        $this->assertSame('42.2405', $coords['lat']);
        $this->assertSame('-8.7200', $coords['lng']);
    }

    public function test_assert_coords_for_venta_fails_when_no_coords_available(): void
    {
        $user = $this->mockCommercialUser('100', 'comercial@test.com');
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        ActionGps::assertCoordsForVentaOrFail(null, null, [], $user);
    }

    public function test_assert_coords_for_venta_allows_empty_for_gps_exempt_commercial_911(): void
    {
        $user = $this->mockCommercialUser('911', 'contratos@gmail.com');

        $coords = ActionGps::assertCoordsForVentaOrFail(null, null, [], $user);

        $this->assertNull($coords['lat']);
        $this->assertNull($coords['lng']);
    }
}
