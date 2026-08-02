<?php

namespace Database\Seeders;

use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

/**
 * 3 filas demo en «Recuperar por imagen» (solo local).
 *
 * Uso:
 *   php artisan db:seed --class=ContratoRecoveryDemoSeeder
 *
 * NO ejecutar en producción. No crea ventas; solo registros pending_add
 * para probar la tabla y el botón Agregar Contrato.
 */
class ContratoRecoveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            $this->command?->error('ContratoRecoveryDemoSeeder bloqueado en production.');

            return;
        }

        if (! Schema::hasTable('contrato_recovery_items')) {
            $this->command?->error('Falta migrar contrato_recovery_items.');

            return;
        }

        // Limpia solo demos anteriores de este seeder
        ContratoRecoveryItem::query()
            ->where('nro_contr_adm', 'like', 'DEMORC%')
            ->delete();

        $actor = User::query()->orderBy('id')->first();
        $comercial = User::query()
            ->whereNotNull('empleado_id')
            ->orderBy('id')
            ->first() ?? $actor;

        $examples = [
            [
                'nro' => 'DEMORC01',
                'dni' => '35301073Y',
                'nombre' => 'Mercedes Guimerans Lorenzo (demo)',
                'importe' => 2272,
                'cuotas' => 39,
                'cuota' => 58.26,
                'productos' => 'laser espalda; laser rodilla; Physio mass manos',
            ],
            [
                'nro' => 'DEMORC02',
                'dni' => '52490318V',
                'nombre' => 'Jose Angel Entenza Carballo (demo)',
                'importe' => 4799,
                'cuotas' => 123,
                'cuota' => 89,
                'productos' => 'Aspiradora; Laser rodilla; Colchón 150x190',
            ],
            [
                'nro' => 'DEMORC03',
                'dni' => 'DEMOBNT01',
                'nombre' => 'María Demo Nota Uno (demo recovery)',
                'importe' => 1500,
                'cuotas' => 24,
                'cuota' => 62.5,
                'productos' => 'Producto demo recuperación',
            ],
        ];

        foreach ($examples as $ex) {
            $dni = mb_strtoupper(trim($ex['dni']));
            $customer = Customer::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
                ->orderBy('id')
                ->first();

            $reviewed = [
                'dni' => $dni,
                'nro_contr_adm' => $ex['nro'],
                'cliente_nombre' => $ex['nombre'],
                'fecha_venta' => now()->subDays(10)->toDateString(),
                'fecha_entrega' => now()->subDays(7)->toDateString(),
                'importe_total' => $ex['importe'],
                'entrada' => 0,
                'cuota_mensual' => $ex['cuota'],
                'num_cuotas' => $ex['cuotas'],
                'iban' => null,
                'productos_texto' => $ex['productos'],
                'comercial_id' => $comercial?->id,
                'comercial_codes' => $comercial?->empleado_id,
                'observaciones' => 'Seed local ContratoRecoveryDemoSeeder — no usar en prod',
            ];

            ContratoRecoveryItem::query()->create([
                'status' => ContratoRecoveryItem::STATUS_PENDING_ADD,
                'documents' => [
                    [
                        'type' => 'app_contract',
                        'path' => 'contract-recovery/demo/'.$ex['nro'].'_app.jpg',
                        'label' => 'demo',
                    ],
                ],
                'extracted_json' => $reviewed,
                'reviewed_json' => $reviewed,
                'dni' => $dni,
                'nro_contr_adm' => $ex['nro'],
                'cliente_nombre' => $ex['nombre'],
                'customer_id' => $customer?->id,
                'comercial_id' => $comercial?->id,
                'created_by_user_id' => $actor?->id,
            ]);
        }

        $this->command?->info('ContratoRecoveryDemoSeeder (local):');
        $this->command?->info('  · 3 registros DEMORC01–03 en estado «Pendiente de agregar»');
        $this->command?->info('  · URL: /superAdmin/recuperar-contrato-imagen');
        $this->command?->warn('  · «Agregar Contrato» puede fallar sin archivos reales / sin cliente DNI; sirve para ver la tabla.');
    }
}
