<?php

namespace Database\Seeders;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Enums\NoteStatus;
use App\Enums\OrigenVenta;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\CustomerSoftDelete;
use App\Support\NoteSoftDelete;
use App\Support\VentaSoftDelete;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de ejemplo para SuperAdmin:
 * - Notas borradas
 * - Contratos borrados
 * - Clientes borrados
 *
 * Uso:
 *   php artisan db:seed --class=BorradosDemoSeeder
 *
 * Es idempotente: borra y recrea solo los registros marcados DEMOB*.
 */
class BorradosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = $this->resolveActor();

        $this->cleanupPreviousDemo();

        $this->seedClientesBorrados($actor);
        $this->seedNotasBorradas($actor);
        $this->seedContratosBorrados($actor);

        $this->command?->info('Demo borrados creada:');
        $this->command?->info('  · 5 clientes en Clientes borrados (DNI DEMOBCL01–05)');
        $this->command?->info('  · 5 notas en Notas borradas (# 99501–99505)');
        $this->command?->info('  · 5 contratos en Contratos borrados (DEMOBCT01–05)');
        $this->command?->info("  · Borrados por: {$actor->empleado_id} {$actor->name} {$actor->last_name}");
    }

    protected function resolveActor(): User
    {
        $user = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'sales_manager']))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if ($user) {
            return $user;
        }

        $user = User::query()->create([
            'name' => 'Demo',
            'last_name' => 'Borrados',
            'email' => 'demo.borrados@example.com',
            'empleado_id' => '999',
            'phone' => '699999999',
            'password' => Hash::make('123456'),
        ]);

        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole('admin');
            } catch (\Throwable) {
                // Roles pueden no existir aún
            }
        }

        return $user;
    }

    protected function cleanupPreviousDemo(): void
    {
        // forceDelete está bloqueado en modelos; limpieza directa de filas demo.
        DB::table('ventas')->where('nro_contr_adm', 'like', 'DEMOBCT%')->delete();

        DB::table('notes')->where(function ($q) {
            $q->whereBetween('nro_nota', ['99501', '99515'])
                ->orWhereIn('nro_nota', ['99501', '99502', '99503', '99504', '99505', '99511', '99512', '99513', '99514', '99515']);
        })->delete();

        DB::table('customers')->where('dni', 'like', 'DEMOB%')->delete();
    }

    protected function seedClientesBorrados(User $actor): void
    {
        $examples = [
            ['DEMOBCL01', 'Ana', 'García López', '611100001', '28001', 'Madrid'],
            ['DEMOBCL02', 'Luis', 'Martínez Ruiz', '611100002', '41001', 'Sevilla'],
            ['DEMOBCL03', 'Carmen', 'Sánchez Pérez', '611100003', '08001', 'Barcelona'],
            ['DEMOBCL04', 'Jorge', 'Fernández Díaz', '611100004', '46001', 'Valencia'],
            ['DEMOBCL05', 'Elena', 'Romero Navarro', '611100005', '50001', 'Zaragoza'],
        ];

        foreach ($examples as $i => [$dni, $first, $last, $phone, $cp, $ciudad]) {
            $customer = Customer::query()->create([
                'first_names' => $first,
                'last_names' => $last,
                'phone' => $phone,
                'dni' => $dni,
                'email' => "demo.cliente.borrado{$i}@example.com",
                'postal_code' => $cp,
                'ciudad' => $ciudad,
                'provincia' => $ciudad,
                'primary_address' => 'Calle Demo Borrado '.($i + 1),
                'nro_piso' => ($i + 1).'º A',
            ]);

            CustomerSoftDelete::delete($customer, $actor->id);

            // Fechas escalonadas para ver orden en el listado
            Customer::withTrashed()->whereKey($customer->id)->update([
                'deleted_at' => now()->subDays(5 - $i)->setTime(9 + $i, 15),
            ]);
        }
    }

    protected function seedNotasBorradas(User $actor): void
    {
        $examples = [
            ['99501', 'DEMOBNT01', 'María', 'Demo Nota Uno', '622200001', '28013'],
            ['99502', 'DEMOBNT02', 'Pedro', 'Demo Nota Dos', '622200002', '41002'],
            ['99503', 'DEMOBNT03', 'Lucía', 'Demo Nota Tres', '622200003', '08002'],
            ['99504', 'DEMOBNT04', 'Diego', 'Demo Nota Cuatro', '622200004', '46002'],
            ['99505', 'DEMOBNT05', 'Sofía', 'Demo Nota Cinco', '622200005', '50002'],
        ];

        $comercialId = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
            ->value('id') ?? $actor->id;

        foreach ($examples as $i => [$nroNota, $dni, $first, $last, $phone, $cp]) {
            $customer = Customer::query()->create([
                'first_names' => $first,
                'last_names' => $last,
                'phone' => $phone,
                'dni' => $dni,
                'postal_code' => $cp,
                'ciudad' => 'Demo',
                'provincia' => 'Demo',
                'primary_address' => 'Av. Nota Borrada '.($i + 1),
            ]);

            $note = Note::query()->create([
                'nro_nota' => $nroNota,
                'user_id' => $actor->id,
                'customer_id' => $customer->id,
                'comercial_id' => $comercialId,
                'fuente' => FuenteNotas::CALLE->value,
                'status' => NoteStatus::CONTACTED->value,
                'estado_terminal' => EstadoTerminal::SIN_ESTADO->value,
                'assignment_date' => now()->subDays(10 - $i),
                'visit_date' => now()->subDays(8 - $i),
                'visit_schedule' => 'TD',
                'show_phone' => true,
            ]);

            NoteSoftDelete::delete($note, $actor->id);

            Note::withTrashed()->whereKey($note->id)->update([
                'deleted_at' => now()->subDays(4 - $i)->setTime(10 + $i, 30),
            ]);
        }
    }

    protected function seedContratosBorrados(User $actor): void
    {
        $examples = [
            ['99511', 'DEMOBVT01', 'DEMOBCT01', '77011', 'Alberto', 'Contrato Uno', '633300001'],
            ['99512', 'DEMOBVT02', 'DEMOBCT02', '77012', 'Beatriz', 'Contrato Dos', '633300002'],
            ['99513', 'DEMOBVT03', 'DEMOBCT03', '77013', 'Carlos', 'Contrato Tres', '633300003'],
            ['99514', 'DEMOBVT04', 'DEMOBCT04', '77014', 'Diana', 'Contrato Cuatro', '633300004'],
            ['99515', 'DEMOBVT05', 'DEMOBCT05', '77015', 'Eduardo', 'Contrato Cinco', '633300005'],
        ];

        $comercialId = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
            ->value('id') ?? $actor->id;

        foreach ($examples as $i => [$nroNota, $dni, $nroContrato, $nroCliente, $first, $last, $phone]) {
            $customer = Customer::query()->create([
                'first_names' => $first,
                'last_names' => $last,
                'phone' => $phone,
                'dni' => $dni,
                'postal_code' => '28'.str_pad((string) ($i + 10), 3, '0', STR_PAD_LEFT),
                'ciudad' => 'Madrid',
                'provincia' => 'Madrid',
                'primary_address' => 'Calle Contrato Borrado '.($i + 1),
            ]);

            $note = Note::query()->create([
                'nro_nota' => $nroNota,
                'user_id' => $actor->id,
                'customer_id' => $customer->id,
                'comercial_id' => $comercialId,
                'fuente' => FuenteNotas::CALLE->value,
                'status' => NoteStatus::CONTACTED->value,
                'estado_terminal' => EstadoTerminal::VENTA->value,
                'assignment_date' => now()->subDays(20 - $i),
                'visit_date' => now()->subDays(18 - $i),
                'visit_schedule' => 'Mañana',
                'show_phone' => true,
            ]);

            $venta = Venta::query()->create([
                'note_id' => $note->id,
                'customer_id' => $customer->id,
                'comercial_id' => $comercialId,
                'fecha_venta' => now()->subDays(15 - $i),
                'importe_total' => 900 + ($i * 150),
                'importe_comercial' => 900 + ($i * 150),
                'modalidad_pago' => $i % 2 === 0 ? 'Financiado' : 'Contado',
                'num_cuotas' => $i % 2 === 0 ? 12 : 1,
                'nro_contr_adm' => $nroContrato,
                'nro_cliente_adm' => $nroCliente,
                'origen_venta' => OrigenVenta::VENTA_NORMAL->value,
                'status' => 'VALIDADA',
            ]);

            VentaSoftDelete::delete($venta, $actor->id);

            Venta::withTrashed()->whereKey($venta->id)->update([
                'deleted_at' => now()->subDays(3 - min($i, 3))->setTime(11 + $i, 45),
            ]);
        }
    }
}
