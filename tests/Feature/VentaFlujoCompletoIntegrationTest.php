<?php

namespace Tests\Feature;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Enums\NoteStatus;
use App\Enums\OrigenVenta;
use App\Enums\VendidoPor;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaOferta;
use App\Models\VentaOfertaProducto;
use App\Support\ActionGps;
use App\Support\Filament\GpsActionForm;
use App\Support\SeguimientoRutaDisplay;
use App\Support\VentaFechaVenta;
use App\Support\VentaOrigenResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Flujo completo de contrato: 2 ventas normales + 2 puerta fría por rol (comercial y jefe de equipo).
 *
 * Replica la secuencia de EditNote → CreateVenta y CreateVentaDesdeCero:
 * GPS wizard → creación de venta → ofertas → cálculos → nota en VENTA → seguimiento.
 *
 * Ejecutar:
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Feature/VentaFlujoCompletoIntegrationTest.php
 */
class VentaFlujoCompletoIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private const GPS_VIGO = ['gps_lat' => '42.240598', 'gps_lng' => '-8.720726'];

    private const GPS_MADRID = ['gps_lat' => '40.416800', 'gps_lng' => '-3.703800'];

    private const GPS_SANTIAGO = ['gps_lat' => '42.880500', 'gps_lng' => '-8.545700'];

    private const GPS_BARCELONA = ['gps_lat' => '41.387400', 'gps_lng' => '2.168600'];

    /** @return array<string, array{0: int, 1: string}> */
    public static function rolesWithGpsProvider(): array
    {
        return [
            'comercial' => [9, 'commercial'],
            'jefe_equipo' => [2, 'team_leader'],
        ];
    }

    private function login(User $user): void
    {
        Auth::login($user);
    }

    private function assertCoord(string $expected, mixed $actual): void
    {
        $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.000001);
    }

    /** @param  array{gps_lat: string, gps_lng: string}  $gps */
    private function assertWizardGpsReady(array $gps): void
    {
        $this->assertTrue(GpsActionForm::gpsReadyOnForm($gps));
    }

    private function attachMinimalOfertaIfPossible(Venta $venta): void
    {
        $oferta = Oferta::query()->first();
        $producto = Producto::query()->first();

        if (! $oferta || ! $producto) {
            return;
        }

        $ventaOferta = VentaOferta::create([
            'venta_id' => $venta->id,
            'oferta_id' => $oferta->id,
            'puntos' => 10,
        ]);

        VentaOfertaProducto::create([
            'venta_oferta_id' => $ventaOferta->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'puntos_linea' => 5,
            'vendido_por' => VendidoPor::Comercial,
        ]);
    }

    /** Misma secuencia post-create que CreateVenta / CreateVentaDesdeCero. */
    private function finalizeVentaPipeline(Venta $venta, Customer $customer): Venta
    {
        $this->attachMinimalOfertaIfPossible($venta);

        $venta->recomputarImportesDesdeOfertas();
        $venta->calcularComisiones(true);
        $venta->recomputarVtasRepYEsp()->recalcularVtasAcumuladas(true);
        $venta->calcularPas(true);

        $entrada = (float) ($venta->entrada ?? 0);
        $montoExtra = (float) ($venta->monto_extra ?? 0);
        $venta->total_final = round(((float) $venta->importe_total - $entrada) + $montoExtra, 2);
        $venta->cuota_final = (int) $venta->num_cuotas > 0
            ? round($venta->total_final / (int) $venta->num_cuotas, 2)
            : null;

        if (empty($venta->nro_contr_adm) && ! empty($venta->nro_contrato)) {
            $venta->nro_contr_adm = $venta->nro_contrato;
        }

        if (empty($venta->nro_cliente_adm) && ! empty($customer->nro_cliente)) {
            $venta->nro_cliente_adm = $customer->nro_cliente;
        }

        $venta->refreshEstadoEntrega();
        $venta->save();

        return $venta->fresh(['note', 'customer', 'ventaOfertas.productos']);
    }

    /**
     * Vía venta normal: nota asignada → acción Venta (GPS) → wizard CreateVenta.
     *
     * @return array{note: Note, venta: Venta}
     */
    private function completeVentaNormalFlow(User $actor, int $sequence, array $gps): array
    {
        $customer = Customer::factory()->create();
        $importe = 400.0 + ($sequence * 50);

        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'user_id' => $actor->id,
            'fuente' => FuenteNotas::CALLE->value,
            'assignment_date' => now(),
            'estado_terminal' => null,
            'reten' => false,
        ]);

        $this->assertWizardGpsReady($gps);

        // EditNote: acción «Venta» guarda GPS en la nota antes del wizard
        ['lat' => $noteLat, 'lng' => $noteLng] = ActionGps::resolve($gps, $actor);
        $note->update(['lat' => $noteLat, 'lng' => $noteLng]);

        // CreateVenta: coordenadas del wizard (prioridad sobre la nota)
        ['lat' => $ventaLat, 'lng' => $ventaLng] = ActionGps::coordsForVenta(
            $note->lat,
            $note->lng,
            $gps,
            $actor,
        );

        $cuotas = 12;
        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'lat' => $ventaLat,
            'lng' => $ventaLng,
            'fecha_venta' => VentaFechaVenta::normalizeOnCreate(),
            'importe_total' => $importe,
            'importe_comercial' => $importe,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => $cuotas,
            'cuota_mensual' => round($importe / $cuotas, 2),
            'motivo_venta' => 'Test flujo venta normal',
            'origen_venta' => OrigenVenta::VENTA_NORMAL,
        ]);

        $venta = $this->finalizeVentaPipeline($venta, $customer);

        $note->update([
            'estado_terminal' => EstadoTerminal::VENTA,
            'reten' => false,
            'comercial_id' => $actor->id,
        ]);

        VentaOrigenResolver::repairMislabeledFuente($note->fresh());

        return [
            'note' => $note->fresh()->load('venta'),
            'venta' => $venta,
        ];
    }

    /**
     * Vía puerta fría: CreateVentaDesdeCero — cliente nuevo + nota PtaFria + venta.
     *
     * @return array{note: Note, venta: Venta, customer: Customer}
     */
    private function completePuertaFriaFlow(User $actor, int $sequence, array $gps): array
    {
        $phone = '6' . str_pad((string) (10000000 + ($actor->id * 10) + $sequence), 8, '0', STR_PAD_LEFT);
        $importe = 350.0 + ($sequence * 75);

        $customer = Customer::factory()->create([
            'first_names' => 'PF FLUJO',
            'last_names' => "TEST {$actor->id}-{$sequence}",
            'phone' => $phone,
        ]);

        $this->assertWizardGpsReady($gps);
        ['lat' => $ventaLat, 'lng' => $ventaLng] = ActionGps::coordsForVenta(
            null,
            null,
            $gps,
            $actor,
        );

        $fechaVenta = VentaFechaVenta::normalizeOnCreate();

        $note = Note::factory()->create([
            'user_id' => $actor->id,
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'status' => NoteStatus::CONTACTED->value,
            'assignment_date' => now(),
            'estado_terminal' => EstadoTerminal::VENTA,
            'fuente' => FuenteNotas::PTA_FRIA->value,
            'reten' => false,
            'lat' => $ventaLat,
            'lng' => $ventaLng,
            'created_at' => $fechaVenta,
            'updated_at' => $fechaVenta,
        ]);

        $cuotas = 12;
        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'lat' => $ventaLat,
            'lng' => $ventaLng,
            'fecha_venta' => $fechaVenta,
            'created_at' => $fechaVenta,
            'updated_at' => $fechaVenta,
            'importe_total' => $importe,
            'importe_comercial' => $importe,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => $cuotas,
            'cuota_mensual' => round($importe / $cuotas, 2),
            'motivo_venta' => 'Test flujo puerta fría',
            'origen_venta' => OrigenVenta::PUERTA_FRIA,
        ]);

        $venta = $this->finalizeVentaPipeline($venta, $customer);

        return [
            'note' => $note->fresh()->load('venta'),
            'venta' => $venta,
            'customer' => $customer->fresh(),
        ];
    }

    private function assertVentaNormalFlowCompleted(
        array $result,
        User $actor,
        array $gps,
        int $sequence,
    ): void {
        /** @var Note $note */
        $note = $result['note'];
        /** @var Venta $venta */
        $venta = $result['venta'];

        $this->assertSame(EstadoTerminal::VENTA->value, $note->estado_terminal->value ?? $note->estado_terminal);
        $this->assertSame($actor->id, $note->comercial_id);
        $this->assertFalse((bool) $note->reten);
        $this->assertSame($venta->id, $note->venta?->id);

        $this->assertSame(OrigenVenta::VENTA_NORMAL, $venta->origen_venta);
        $this->assertSame(OrigenVenta::VENTA_NORMAL, VentaOrigenResolver::origenForCreateFromNote($note));
        $this->assertNotSame(FuenteNotas::PTA_FRIA, VentaOrigenResolver::fuenteDisplayForVenta($venta));

        $this->assertCoord($gps['gps_lat'], $venta->lat);
        $this->assertCoord($gps['gps_lng'], $venta->lng);
        $this->assertNotEmpty($venta->nro_contr_adm);

        $seguimiento = SeguimientoRutaDisplay::declaredGpsCoords(
            $note->load('venta'),
            Carbon::today(),
            'venta',
        );
        $this->assertCoord($gps['gps_lat'], $seguimiento['gps_lat']);

        $this->assertGreaterThan(0, (float) $venta->total_final);

        if (Oferta::query()->exists() && Producto::query()->exists()) {
            $this->assertTrue($venta->ventaOfertas->isNotEmpty(), 'Debe haber oferta si el catálogo existe en BD.');
        }
    }

    private function assertPuertaFriaFlowCompleted(
        array $result,
        User $actor,
        array $gps,
    ): void {
        /** @var Note $note */
        $note = $result['note'];
        /** @var Venta $venta */
        $venta = $result['venta'];
        /** @var Customer $customer */
        $customer = $result['customer'];

        $this->assertSame(EstadoTerminal::VENTA->value, $note->estado_terminal->value ?? $note->estado_terminal);
        $this->assertSame($actor->id, $note->comercial_id);
        $this->assertSame(FuenteNotas::PTA_FRIA->value, $note->fuente->value ?? $note->fuente);
        $this->assertSame($venta->id, $note->venta?->id);

        $this->assertSame(OrigenVenta::PUERTA_FRIA, $venta->origen_venta);
        $this->assertSame(FuenteNotas::PTA_FRIA, VentaOrigenResolver::fuenteDisplayForVenta($venta));

        $this->assertCoord($gps['gps_lat'], $venta->lat);
        $this->assertCoord($gps['gps_lng'], $venta->lng);
        $this->assertNotEmpty($venta->nro_contr_adm);
        $this->assertNotEmpty($customer->nro_cliente);

        $seguimiento = SeguimientoRutaDisplay::declaredGpsCoords(
            $note->load('venta'),
            Carbon::today(),
            'venta',
        );
        $this->assertCoord($gps['gps_lat'], $seguimiento['gps_lat']);

        $this->assertGreaterThan(0, (float) $venta->total_final);
    }

    /**
     * @dataProvider rolesWithGpsProvider
     */
    public function test_dos_ventas_normales_y_dos_puerta_fria_completan_flujo_por_rol(int $userId, string $roleName): void
    {
        $actor = User::findOrFail($userId);
        $this->login($actor);

        $this->assertTrue($actor->hasRole($roleName));
        $this->assertTrue(ActionGps::shouldRegisterGps($actor));

        $normalGpsSets = [self::GPS_VIGO, self::GPS_MADRID];
        $pfGpsSets = [self::GPS_SANTIAGO, self::GPS_BARCELONA];

        $ventaNormalIds = [];
        $puertaFriaIds = [];

        foreach ($normalGpsSets as $index => $gps) {
            $sequence = $index + 1;
            $result = $this->completeVentaNormalFlow($actor, $sequence, $gps);
            $this->assertVentaNormalFlowCompleted($result, $actor, $gps, $sequence);
            $ventaNormalIds[] = $result['venta']->id;
        }

        foreach ($pfGpsSets as $index => $gps) {
            $sequence = $index + 1;
            $result = $this->completePuertaFriaFlow($actor, $sequence, $gps);
            $this->assertPuertaFriaFlowCompleted($result, $actor, $gps);
            $puertaFriaIds[] = $result['venta']->id;
        }

        $this->assertCount(2, array_unique($ventaNormalIds));
        $this->assertCount(2, array_unique($puertaFriaIds));

        $this->assertSame(2, Venta::query()
            ->whereIn('id', $ventaNormalIds)
            ->where('origen_venta', OrigenVenta::VENTA_NORMAL->value)
            ->where('comercial_id', $actor->id)
            ->count());

        $this->assertSame(2, Venta::query()
            ->whereIn('id', $puertaFriaIds)
            ->where('origen_venta', OrigenVenta::PUERTA_FRIA->value)
            ->where('comercial_id', $actor->id)
            ->count());
    }
}
