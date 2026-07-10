<?php

namespace Tests\Unit;

use App\Enums\EstadoTerminal;
use App\Enums\OrigenVenta;
use App\Filament\Commercial\Resources\VentaDesdeCeroResource;
use App\Filament\Commercial\Resources\VentaResource;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\ActionGps;
use App\Support\Filament\GpsActionForm;
use App\Support\SeguimientoRutaDisplay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Mockery;
use Tests\TestCase;

class VentaGpsFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockCommercialUser(string $empleadoId = '100', string $email = 'comercial@test.com'): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 99;
        $user->empleado_id = $empleadoId;
        $user->email = $email;
        $user->shouldReceive('hasRole')->with('gerente')->andReturn(false);
        $user->shouldReceive('hasRole')->with('commercial')->andReturn(true);
        $user->shouldReceive('hasAnyRole')->with(['commercial', 'team_leader'])->andReturn(true);

        return $user;
    }

    private function mockTeamLeaderUser(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 88;
        $user->empleado_id = '200';
        $user->email = 'jefe@test.com';
        $user->shouldReceive('hasRole')->with('gerente')->andReturn(false);
        $user->shouldReceive('hasRole')->with('commercial')->andReturn(false);
        $user->shouldReceive('hasAnyRole')->with(['commercial', 'team_leader'])->andReturn(true);

        return $user;
    }

    private function mockGpsExemptCommercialUser(): User
    {
        return $this->mockCommercialUser('911', 'contratos@gmail.com');
    }

    private function mockGerenteUser(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->empleado_id = '001';
        $user->shouldReceive('hasRole')->with('gerente')->andReturn(true);

        return $user;
    }

    private function loginCommercial(): User
    {
        $user = $this->mockCommercialUser();
        $this->actingAs($user);

        return $user;
    }

    /** Ejemplo 1: venta normal — GPS del wizard se guarda en ventas.lat/lng */
    public function test_venta_normal_comercial_con_gps_en_wizard_resuelve_coordenadas(): void
    {
        App::shouldReceive('environment')->with('local')->andReturn(false);

        $commercial = $this->mockCommercialUser();
        $wizardData = [
            'gps_lat' => '42.1111',
            'gps_lng' => '-8.2222',
        ];

        $coords = ActionGps::coordsForVenta('41.0000', '-8.0000', $wizardData, $commercial);

        $this->assertSame('42.1111', $coords['lat']);
        $this->assertSame('-8.2222', $coords['lng']);
        $this->assertTrue(GpsActionForm::gpsReadyOnForm($wizardData));
    }

    /** Ejemplo 1: venta normal — sin GPS en wizard el botón Crear queda bloqueado */
    public function test_venta_normal_sin_gps_bloquea_crear(): void
    {
        $this->loginCommercial();

        $this->assertFalse(GpsActionForm::gpsReadyOnForm([]));
        $this->assertFalse(GpsActionForm::gpsReadyOnForm(['gps_lat' => '42.1']));
        $this->assertCount(3, GpsActionForm::ventaWizardFields());
    }

    /** Ejemplo 1: venta normal — fallback a coords de nota si wizard vacío en prod */
    public function test_venta_normal_sin_gps_wizard_usa_coords_nota_como_fallback(): void
    {
        App::shouldReceive('environment')->with('local')->andReturn(false);

        $commercial = $this->mockCommercialUser();

        $coords = ActionGps::coordsForVenta('41.5555', '-8.6666', [], $commercial);

        $this->assertSame('41.5555', $coords['lat']);
        $this->assertSame('-8.6666', $coords['lng']);
    }

    /** Ejemplo 2: puerta fría — misma política GPS que venta normal */
    public function test_puerta_fria_comercial_con_gps_en_wizard_resuelve_coordenadas(): void
    {
        App::shouldReceive('environment')->with('local')->andReturn(false);

        $commercial = $this->mockCommercialUser();
        $wizardData = [
            'gps_lat' => '43.3333',
            'gps_lng' => '-7.7777',
        ];

        $coords = ActionGps::coordsForVenta(null, null, $wizardData, $commercial);

        $this->assertSame('43.3333', $coords['lat']);
        $this->assertSame('-7.7777', $coords['lng']);
    }

    /** Ejemplo 2: puerta fría — wizard incluye campos GPS obligatorios */
    public function test_puerta_fria_wizard_incluye_campos_gps_para_comercial(): void
    {
        $this->loginCommercial();

        $this->assertCount(3, GpsActionForm::ventaWizardFields());
        $this->assertGreaterThanOrEqual(3, count(VentaResource::step2Schema()));
        $this->assertGreaterThanOrEqual(3, count(VentaDesdeCeroResource::step2Schema()));

        $fieldClasses = collect(VentaDesdeCeroResource::step2Schema())
            ->map(fn ($c) => $c::class)
            ->all();

        $this->assertContains(\Filament\Forms\Components\Hidden::class, $fieldClasses);
        $this->assertContains(\Filament\Forms\Components\View::class, $fieldClasses);
    }

    /** Seguimiento: venta con GPS → IR verde (coords disponibles) */
    public function test_seguimiento_muestra_gps_cuando_venta_tiene_coordenadas(): void
    {
        $note = new Note(['id' => 1, 'nro_nota' => '99999']);
        $venta = new Venta([
            'lat' => '42.9999',
            'lng' => '-8.8888',
            'fecha_venta' => Carbon::parse('2026-06-17'),
            'origen_venta' => OrigenVenta::VENTA_NORMAL,
        ]);
        $note->setRelation('venta', $venta);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::parse('2026-06-17'), 'venta');

        $this->assertSame('42.9999', $gps['gps_lat']);
        $this->assertSame('-8.8888', $gps['gps_lng']);
    }

    /** Gerente nunca registra GPS en ventas */
    public function test_gerente_no_registra_gps_en_venta(): void
    {
        $gerente = $this->mockGerenteUser();

        $this->assertFalse(ActionGps::shouldRegisterGps($gerente));
        $this->assertSame(
            ['lat' => null, 'lng' => null],
            ActionGps::coordsForVenta('41.0', '-8.0', ['gps_lat' => '42.1', 'gps_lng' => '-8.2'], $gerente),
        );

        $this->actingAs($gerente);
        $this->assertSame([], GpsActionForm::ventaWizardFields());
    }

    /** Modal venta desde nota: GPS capturado habilita confirmar */
    public function test_modal_venta_nota_gps_listo_habilita_confirmar(): void
    {
        $livewire = new class
        {
            public array $mountedActionsData = [
                0 => ['gps_lat' => '42.1', 'gps_lng' => '-8.3'],
            ];
        };

        $this->loginCommercial();

        $this->assertTrue(GpsActionForm::gpsReadyOnLivewire($livewire));
    }

    public function test_estados_y_origenes_de_venta_son_los_esperados(): void
    {
        $this->assertSame('venta', EstadoTerminal::VENTA->value);
        $this->assertSame('puerta_fria', OrigenVenta::PUERTA_FRIA->value);
        $this->assertSame('venta_normal', OrigenVenta::VENTA_NORMAL->value);
    }

    /** Comercial 911 / contratos@gmail.com: venta de prueba sin GPS */
    public function test_comercial_911_puede_declarar_venta_sin_gps(): void
    {
        App::shouldReceive('environment')->with('local')->andReturn(false);

        $exempt = $this->mockGpsExemptCommercialUser();
        $this->actingAs($exempt);

        $this->assertFalse(ActionGps::shouldRegisterGps($exempt));
        $this->assertSame([], GpsActionForm::ventaWizardFields());
        $this->assertTrue(GpsActionForm::gpsReadyOnForm([]));
        $this->assertTrue(GpsActionForm::gpsReadyOnForm(['gps_lat' => null, 'gps_lng' => null]));

        $coords = ActionGps::coordsForVenta('41.5555', '-8.6666', [
            'gps_lat' => '42.1111',
            'gps_lng' => '-8.2222',
        ], $exempt);

        $this->assertNull($coords['lat']);
        $this->assertNull($coords['lng']);
    }

    /** Comercial normal: venta sigue exigiendo GPS en wizard */
    public function test_comercial_normal_sigue_exigiendo_gps_en_venta(): void
    {
        $commercial = $this->mockCommercialUser();
        $this->actingAs($commercial);

        $this->assertTrue(ActionGps::shouldRegisterGps($commercial));
        $this->assertCount(3, GpsActionForm::ventaWizardFields());
        $this->assertFalse(GpsActionForm::gpsReadyOnForm([]));

        $wizardData = ['gps_lat' => '42.1111', 'gps_lng' => '-8.2222'];
        $this->assertTrue(GpsActionForm::gpsReadyOnForm($wizardData));

        $coords = ActionGps::coordsForVenta(null, null, $wizardData, $commercial);
        $this->assertSame('42.1111', $coords['lat']);
        $this->assertSame('-8.2222', $coords['lng']);
    }

    /** Jefe de equipo: venta sigue exigiendo GPS */
    public function test_jefe_de_equipo_sigue_exigiendo_gps_en_venta(): void
    {
        $teamLeader = $this->mockTeamLeaderUser();
        $this->actingAs($teamLeader);

        $this->assertTrue(ActionGps::shouldRegisterGps($teamLeader));
        $this->assertCount(3, GpsActionForm::ventaWizardFields());
        $this->assertFalse(GpsActionForm::gpsReadyOnForm([]));

        $wizardData = ['gps_lat' => '40.4168', 'gps_lng' => '-3.7038'];
        $this->assertTrue(GpsActionForm::gpsReadyOnForm($wizardData));

        $coords = ActionGps::coordsForVenta(null, null, $wizardData, $teamLeader);
        $this->assertSame('40.4168', $coords['lat']);
        $this->assertSame('-3.7038', $coords['lng']);
    }

    /** Modal venta desde nota: 911 no necesita GPS capturado para confirmar */
    public function test_modal_venta_nota_911_sin_gps_habilita_confirmar(): void
    {
        $livewire = new class
        {
            public array $mountedActionsData = [0 => []];
        };

        $exempt = $this->mockGpsExemptCommercialUser();
        $this->actingAs($exempt);

        $this->assertTrue(GpsActionForm::gpsReadyOnLivewire($livewire));
        $this->assertSame([], GpsActionForm::fields());
    }
}
