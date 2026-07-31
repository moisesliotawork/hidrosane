<?php

namespace Tests\Unit;

use App\Support\VentaBackupRecovery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VentaBackupRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'database.connections.mysql_backup' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        DB::purge('sqlite');
        DB::purge('mysql_backup');

        $this->createSchema(DB::connection());
        $this->createSchema(DB::connection('mysql_backup'));
    }

    private function createSchema($connection): void
    {
        Schema::connection($connection->getName())->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::connection($connection->getName())->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('nro_cliente')->nullable();
            $table->string('first_names')->nullable();
            $table->string('last_names')->nullable();
            $table->string('dni')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($connection->getName())->create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('comercial_id')->nullable();
            $table->string('nro_nota')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($connection->getName())->create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('comercial_id')->nullable();
            $table->string('nro_contr_adm')->nullable();
            $table->timestamp('fecha_venta')->nullable();
            $table->string('estado_venta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($connection->getName())->create('ofertas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
        });

        Schema::connection($connection->getName())->create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
        });

        Schema::connection($connection->getName())->create('venta_ofertas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('oferta_id');
            $table->unsignedInteger('puntos')->default(0);
            $table->timestamps();
        });

        Schema::connection($connection->getName())->create('venta_oferta_productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_oferta_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedInteger('cantidad')->default(1);
            $table->unsignedInteger('cantidad_entregada')->default(0);
            $table->unsignedInteger('puntos_linea')->default(0);
            $table->string('vendido_por')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection->getName())->create('transaction_venta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_contrato');
            $table->unsignedBigInteger('id_contrato_asoc');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_detects_missing_and_recovers_contract(): void
    {
        $backup = DB::connection('mysql_backup');
        $prod = DB::connection();

        $backup->table('users')->insert(['id' => 1, 'name' => 'Comercial']);
        $prod->table('users')->insert(['id' => 1, 'name' => 'Comercial']);
        $prod->table('ofertas')->insert(['id' => 2, 'nombre' => 'Oferta']);
        $prod->table('productos')->insert(['id' => 112, 'nombre' => 'Prod']);
        $backup->table('ofertas')->insert(['id' => 2, 'nombre' => 'Oferta']);
        $backup->table('productos')->insert(['id' => 112, 'nombre' => 'Prod']);

        $backup->table('customers')->insert([
            'id' => 50,
            'nro_cliente' => '00725',
            'first_names' => 'Mercedes',
            'last_names' => 'Guimerans',
            'dni' => '35301073Y',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $backup->table('notes')->insert([
            'id' => 80,
            'user_id' => 1,
            'customer_id' => 50,
            'comercial_id' => 1,
            'nro_nota' => '06031',
            'status' => 'contacted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $backup->table('ventas')->insert([
            'id' => 207,
            'note_id' => 80,
            'customer_id' => 50,
            'comercial_id' => 1,
            'nro_contr_adm' => '1189',
            'fecha_venta' => '2025-10-02 14:21:54',
            'estado_venta' => 'facturado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $voId = $backup->table('venta_ofertas')->insertGetId([
            'venta_id' => 207,
            'oferta_id' => 2,
            'puntos' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $backup->table('venta_oferta_productos')->insert([
            'venta_oferta_id' => $voId,
            'producto_id' => 112,
            'cantidad' => 1,
            'cantidad_entregada' => 0,
            'puntos_linea' => 2,
            'vendido_por' => 'comercial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recovery = VentaBackupRecovery::make('mysql_backup');

        $missing = $recovery->missingVentas();
        $this->assertCount(1, $missing);
        $this->assertSame('1189', $missing->first()->nro_contr_adm);

        $dry = $recovery->recoverOne($missing->first(), dryRun: true);
        $this->assertSame('would_insert', $dry['status']);

        $done = $recovery->recoverOne($missing->first(), dryRun: false);
        $this->assertSame('inserted', $done['status']);
        $this->assertTrue($prod->table('ventas')->where('nro_contr_adm', '1189')->exists());
        $this->assertTrue($prod->table('customers')->where('dni', '35301073Y')->exists());
        $this->assertTrue($prod->table('notes')->where('nro_nota', '06031')->exists());
        $this->assertSame(1, $prod->table('venta_ofertas')->count());
        $this->assertSame(1, $prod->table('venta_oferta_productos')->count());
    }
}
