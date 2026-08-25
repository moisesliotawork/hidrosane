<?php

namespace Tests\Unit;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Enums\OrigenVenta;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\ActionGps;
use App\Support\Filament\FechaNacimientoField;
use App\Support\PuertaFriaCustomerResolver;
use App\Support\PuertaFriaCustomerSearch;
use App\Support\VentaFechaVenta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comercial 911 (contratos@gmail.com): búsqueda Puerta Fría + creación de contrato.
 *
 * Cubre el fallo de producción: Class "App\Support\FechaNacimientoField" not found
 * en PuertaFriaCustomerSearch::customerToFormData().
 *
 * Ejecutar (MySQL local; sqlite no soporta MODIFY ENUM de migraciones):
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Unit/PuertaFriaCommercial911Test.php
 */
class PuertaFriaCommercial911Test extends TestCase
{
    use DatabaseTransactions;

    private function commercial911(): User
    {
        $user = User::factory()->create([
            'empleado_id' => ActionGps::GPS_EXEMPT_COMMERCIAL_EMPLEADO_ID,
            'email' => ActionGps::GPS_EXEMPT_COMMERCIAL_EMAIL,
            'name' => 'ADMI',
            'last_name' => 'CONTR',
        ]);

        Role::findOrCreate('commercial');
        $user->assignRole('commercial');

        return $user->fresh();
    }

    private function uniqueDigits(int $length = 9): string
    {
        $prefix = $length === 9 ? '6' : '';
        $rest = substr(preg_replace('/\D/', '', (string) microtime(true).random_int(1000, 9999)), 0, $length - strlen($prefix));

        return $prefix.str_pad($rest, $length - strlen($prefix), '0');
    }

    public function test_fecha_nacimiento_field_resuelve_desde_namespace_filament(): void
    {
        $this->assertTrue(class_exists(FechaNacimientoField::class));
        $this->assertSame('1953-07-30', FechaNacimientoField::normalizeForStorage('30/07/1953'));
    }

    public function test_customer_to_form_data_no_falla_con_fecha_nac(): void
    {
        $customer = Customer::factory()->create([
            'first_names' => 'Maria Angeles',
            'last_names' => 'Caldas Castineira '.Str::random(6),
            'dni' => strtoupper(Str::random(8)).'J',
            'fecha_nac' => '1953-07-30',
            'phone' => $this->uniqueDigits(),
            'secondary_phone' => $this->uniqueDigits(),
            'phone1_commercial' => $this->uniqueDigits(),
        ]);

        $data = PuertaFriaCustomerSearch::customerToFormData($customer);

        $this->assertSame($customer->id, $data['pf_existing_customer_id']);
        $this->assertSame('Maria Angeles', $data['first_names']);
        $this->assertSame($customer->last_names, $data['last_names']);
        $this->assertSame($customer->dni, $data['dni']);
        $this->assertNotEmpty($data['fecha_nac']);
        $this->assertSame('1953-07-30', FechaNacimientoField::normalizeForStorage($data['fecha_nac']));
    }

    public function test_comercial_911_puede_crear_contrato_puerta_fria_sin_gps(): void
    {
        $commercial = $this->commercial911();
        $this->actingAs($commercial);

        $this->assertTrue(ActionGps::isGpsExempt($commercial));
        $this->assertFalse(ActionGps::shouldRegisterGps($commercial));

        $coords = ActionGps::assertCoordsForVentaOrFail(null, null, [], $commercial);
        $this->assertNull($coords['lat']);
        $this->assertNull($coords['lng']);

        $phone = $this->uniqueDigits();
        $dni = strtoupper(Str::random(8)).'Z';

        $formData = [
            'first_names' => 'Cliente',
            'last_names' => 'Puerta Fria 911 '.Str::random(4),
            'phone1_commercial' => implode(' ', str_split($phone, 3)),
            'dni' => $dni,
            'fecha_nac' => FechaNacimientoField::normalizeForStorage('15/03/1960'),
            'primary_address' => 'Calle Test Puerta Fria 1',
            'postal_code' => '36201',
            'ciudad' => 'Vigo',
            'provincia' => 'Pontevedra',
            'pf_existing_customer_id' => null,
        ];

        $this->assertSame('1960-03-15', $formData['fecha_nac']);

        $customer = app(PuertaFriaCustomerResolver::class)->resolveOrCreate(
            $formData,
            $commercial->id,
        );

        $this->assertNotNull($customer->id);
        $this->assertSame('Cliente', $customer->first_names);

        // Prefill como si se hubiera seleccionado el cliente (ruta que fallaba en prod)
        $prefillExisting = PuertaFriaCustomerSearch::customerToFormData($customer);
        $this->assertSame($customer->id, $prefillExisting['pf_existing_customer_id']);
        $this->assertNotEmpty($prefillExisting['fecha_nac']);

        $fechaVenta = VentaFechaVenta::normalizeOnCreate();

        $note = Note::factory()->create([
            'user_id' => $commercial->id,
            'customer_id' => $customer->id,
            'comercial_id' => $commercial->id,
            'estado_terminal' => EstadoTerminal::VENTA,
            'fuente' => FuenteNotas::PTA_FRIA->value,
            'reten' => false,
            'lat' => null,
            'lng' => null,
            'created_at' => $fechaVenta,
            'updated_at' => $fechaVenta,
        ]);

        $importe = 1899.00;
        $cuotas = 39;

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $commercial->id,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'fecha_venta' => $fechaVenta,
            'created_at' => $fechaVenta,
            'updated_at' => $fechaVenta,
            'importe_total' => $importe,
            'importe_comercial' => $importe,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => $cuotas,
            'cuota_mensual' => round($importe / $cuotas, 2),
            'motivo_venta' => 'Test puerta fria comercial 911',
            'origen_venta' => OrigenVenta::PUERTA_FRIA,
        ]);

        $venta = $venta->fresh();

        $this->assertNotNull($venta->id);
        $this->assertNotEmpty($venta->nro_contr_adm);
        $this->assertSame($commercial->id, $venta->comercial_id);
        $this->assertSame($customer->id, $venta->customer_id);
        $this->assertSame(OrigenVenta::PUERTA_FRIA, $venta->origen_venta);
        $this->assertNull($venta->lat);
        $this->assertNull($venta->lng);
        $this->assertSame(EstadoTerminal::VENTA, $note->fresh()->estado_terminal);
        $this->assertSame(FuenteNotas::PTA_FRIA, $note->fresh()->fuente);
    }

    public function test_comercial_911_reutiliza_cliente_existente_en_puerta_fria(): void
    {
        $commercial = $this->commercial911();
        $this->actingAs($commercial);

        $phone = $this->uniqueDigits();
        $existing = Customer::factory()->create([
            'first_names' => 'Ana',
            'last_names' => 'Existente Test '.Str::random(4),
            'dni' => strtoupper(Str::random(8)).'X',
            'fecha_nac' => '1975-01-20',
            'phone1_commercial' => $phone,
        ]);

        $prefill = PuertaFriaCustomerSearch::customerToFormData($existing);

        $resolved = app(PuertaFriaCustomerResolver::class)->resolveOrCreate(
            array_merge($prefill, [
                'phone1_commercial' => implode(' ', str_split($phone, 3)),
            ]),
            $commercial->id,
        );

        $this->assertSame($existing->id, $resolved->id);

        $coords = ActionGps::assertCoordsForVentaOrFail(null, null, [], $commercial);
        $fechaVenta = VentaFechaVenta::normalizeOnCreate();

        $note = Note::factory()->create([
            'user_id' => $commercial->id,
            'customer_id' => $resolved->id,
            'comercial_id' => $commercial->id,
            'estado_terminal' => EstadoTerminal::VENTA,
            'fuente' => FuenteNotas::PTA_FRIA->value,
        ]);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $resolved->id,
            'comercial_id' => $commercial->id,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'fecha_venta' => $fechaVenta,
            'importe_total' => 500,
            'importe_comercial' => 500,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => OrigenVenta::PUERTA_FRIA,
        ]);

        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'customer_id' => $existing->id,
            'comercial_id' => $commercial->id,
            'origen_venta' => 'puerta_fria',
        ]);
    }
}
