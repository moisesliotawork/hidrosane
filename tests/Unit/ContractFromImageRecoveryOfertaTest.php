<?php

namespace Tests\Unit;

use App\Enums\VendidoPor;
use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\ContractRecovery\ContractFromImageRecovery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Requiere MySQL (migraciones con ENUM no corren en sqlite de phpunit.xml).
 *
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= \
 *   php artisan test tests/Unit/ContractFromImageRecoveryOfertaTest.php
 */
class ContractFromImageRecoveryOfertaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Este test requiere MySQL (DB_CONNECTION=mysql).');
        }
    }

    public function test_validate_oferta_productos_requires_oferta_and_product(): void
    {
        $svc = new ContractFromImageRecovery;

        $this->assertSame(
            'Indica al menos una oferta con productos (Editar → Oferta y productos).',
            $svc->validateOfertaProductos([])
        );

        $this->assertSame(
            'Indica al menos una oferta con productos (Editar → Oferta y productos).',
            $svc->validateOfertaProductos([
                'ventaOfertas' => [
                    ['oferta_id' => null, 'productos' => []],
                ],
            ])
        );

        $oferta = Oferta::query()->create([
            'nombre' => 'Oferta test recovery '.uniqid(),
            'puntos_base' => 4,
            'precio_base' => 1899,
            'visible' => true,
        ]);

        $this->assertSame(
            'Cada oferta debe tener al menos un producto.',
            $svc->validateOfertaProductos([
                'ventaOfertas' => [
                    ['oferta_id' => $oferta->id, 'productos' => []],
                ],
            ])
        );
    }

    public function test_validate_requires_nombre_for_producto_externo(): void
    {
        $svc = new ContractFromImageRecovery;

        $oferta = Oferta::query()->create([
            'nombre' => 'Oferta externa '.uniqid(),
            'puntos_base' => 1,
            'precio_base' => 100,
            'visible' => true,
        ]);
        $externo = Producto::query()->firstOrCreate(
            ['nombre' => 'Producto Externo'],
            ['puntos' => 0, 'delete' => false],
        );

        $this->assertSame(
            'Hay «Producto Externo»: indica el nombre en Productos externos.',
            $svc->validateOfertaProductos([
                'ventaOfertas' => [[
                    'oferta_id' => $oferta->id,
                    'productos' => [[
                        'producto_id' => $externo->id,
                        'cantidad' => 1,
                        'puntos_linea' => 0,
                    ]],
                ]],
            ])
        );

        $this->assertNull($svc->validateOfertaProductos([
            'ventaOfertas' => [[
                'oferta_id' => $oferta->id,
                'productos' => [[
                    'producto_id' => $externo->id,
                    'cantidad' => 1,
                    'puntos_linea' => 0,
                ]],
            ]],
            'productos_externos' => ['TV manuscrita 32'],
        ]));
    }

    public function test_add_contract_creates_venta_ofertas_and_productos(): void
    {
        $user = User::factory()->create([
            'name' => 'Recovery',
            'last_name' => 'Test',
            'email' => 'recovery-oferta-'.uniqid().'@test.local',
            'empleado_id' => (string) random_int(100, 999),
        ]);
        $customer = Customer::factory()->create([
            'dni' => 'R'.substr(uniqid(), -7).'E',
            'nro_cliente' => str_pad((string) random_int(90000, 99999), 5, '0', STR_PAD_LEFT),
        ]);
        $oferta = Oferta::query()->create([
            'nombre' => 'Oferta recovery '.uniqid(),
            'puntos_base' => 4,
            'precio_base' => 1899,
            'visible' => true,
        ]);
        $producto = Producto::query()->create([
            'nombre' => 'Producto recovery '.uniqid(),
            'puntos' => 1,
            'delete' => false,
        ]);

        $nro = (string) random_int(90000, 99999);

        $item = ContratoRecoveryItem::query()->create([
            'status' => ContratoRecoveryItem::STATUS_PENDING_ADD,
            'documents' => [],
            'extracted_json' => [],
            'reviewed_json' => [
                'dni' => $customer->dni,
                'nro_contr_adm' => $nro,
                'cliente_nombre' => 'Test Recovery',
                'comercial_id' => $user->id,
                'importe_total' => 1899,
                'entrada' => 0,
                'num_cuotas' => 39,
                'cuota_mensual' => 48.69,
                'productos_texto' => 'TV a mano',
                'ventaOfertas' => [[
                    'oferta_id' => $oferta->id,
                    'puntos' => 4,
                    'productos' => [[
                        'producto_id' => $producto->id,
                        'cantidad' => 1,
                        'puntos_linea' => 1,
                        'vendido_por' => VendidoPor::Comercial->value,
                    ]],
                ]],
            ],
            'dni' => $customer->dni,
            'nro_contr_adm' => $nro,
            'cliente_nombre' => 'Test Recovery',
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $result = app(ContractFromImageRecovery::class)->addContract($item);

        $this->assertTrue($result['ok'], $result['message'] ?? '');
        $this->assertArrayHasKey('venta_id', $result);

        $venta = Venta::query()->with(['ventaOfertas.productos'])->findOrFail($result['venta_id']);
        $this->assertSame($nro, $venta->nro_contr_adm);
        $this->assertCount(1, $venta->ventaOfertas);
        $this->assertSame($oferta->id, $venta->ventaOfertas->first()->oferta_id);
        $this->assertCount(1, $venta->ventaOfertas->first()->productos);
        $this->assertSame($producto->id, $venta->ventaOfertas->first()->productos->first()->producto_id);
        $this->assertSame(['TV a mano'], $venta->productos_externos);
    }

    public function test_normalize_productos_externos_prefers_structured_list(): void
    {
        $svc = new ContractFromImageRecovery;
        $m = new ReflectionMethod(ContractFromImageRecovery::class, 'normalizeProductosExternos');
        $m->setAccessible(true);

        $this->assertSame(
            ['Depurador Vita'],
            $m->invoke($svc, [
                'productos_externos' => ['Depurador Vita'],
                'productos_texto' => 'OCR basura',
            ])
        );

        $this->assertSame(
            ['OCR solo'],
            $m->invoke($svc, [
                'productos_texto' => 'OCR solo',
            ])
        );
    }
}
