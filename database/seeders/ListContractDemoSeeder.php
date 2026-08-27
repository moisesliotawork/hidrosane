<?php

namespace Database\Seeders;

use App\Enums\EstadoTerminal;
use App\Enums\EstadoVenta;
use App\Enums\FuenteNotas;
use App\Enums\NoteStatus;
use App\Enums\OrigenVenta;
use App\Models\ContratoMesBaseline;
use App\Models\ContratoMesVariacionItem;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\ContratosPorMesStats;
use App\Support\VentaSoftDelete;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de ejemplo para SuperAdmin:
 * - ListContract
 * - Contratos/MES con los 3 casos de VARIACIÓN:
 *     · sin cambio (azul)
 *     · menos contratos / soft-delete (rojo)
 *     · más contratos (verde)
 *
 * Uso:
 *   php artisan db:seed --class=ListContractDemoSeeder
 *
 * Idempotente: limpia y recrea solo registros DEMOLC*.
 */
class ListContractDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = $this->resolveActor();
        $comercial = $this->resolveComercial($actor);

        $this->cleanupPreviousDemo();

        $created = $this->seedDemoContratos($actor, $comercial);
        $this->applyVariacionExamples($actor, $comercial);

        $this->command?->info('Demo ListContract / Contratos/MES creada:');
        $this->command?->info("  · {$created}+ contratos demo (nro DEMOLC…)");
        $this->command?->info('  · Casos de VARIACIÓN:');
        $this->command?->info('      · mes -2/-3 → sin cambio (0, azul)');
        $this->command?->info('      · mes -1 → menos contratos (-2, rojo) vía soft-delete');
        $this->command?->info('      · mes actual → más contratos (+2, verde)');
        $this->command?->info('  · Sección «Variaciones de Contratos»: 4 detalles (2 soft-delete + 2 nuevos)');
        $this->command?->info("  · Comercial: {$comercial->empleado_id} {$comercial->name} {$comercial->last_name}");

        $highlight = [
            now()->format('Y-m'),
            now()->subMonth()->format('Y-m'),
            now()->subMonths(2)->format('Y-m'),
            now()->subMonths(3)->format('Y-m'),
        ];

        foreach (ContratosPorMesStats::rows() as $row) {
            if (! in_array((string) $row->mes_key, $highlight, true)) {
                continue;
            }
            $var = (int) $row->variacion;
            $sign = $var > 0 ? "+{$var}" : (string) $var;
            $this->command?->info("      {$row->mes_key}: total={$row->total} base={$row->baseline_total} var={$sign}");
        }
    }

    protected function resolveActor(): User
    {
        $user = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['app_support', 'admin', 'sales_manager']))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if ($user) {
            return $user;
        }

        $user = User::query()->create([
            'name' => 'Demo',
            'last_name' => 'ListContract',
            'email' => 'demo.listcontract@example.com',
            'empleado_id' => '998',
            'phone' => '698888888',
            'password' => Hash::make('123456'),
        ]);

        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole('app_support');
            } catch (\Throwable) {
                //
            }
        }

        return $user;
    }

    protected function resolveComercial(User $fallback): User
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
            ->orderBy('id')
            ->first()
            ?? $fallback;
    }

    protected function cleanupPreviousDemo(): void
    {
        $ventaIds = DB::table('ventas')
            ->where('nro_contr_adm', 'like', 'DEMOLC%')
            ->pluck('id');

        $noteIds = DB::table('notes')
            ->whereBetween('nro_nota', ['99601', '99699'])
            ->pluck('id');

        $customerIds = DB::table('customers')
            ->where('dni', 'like', 'DEMOLC%')
            ->pluck('id');

        if ($ventaIds->isNotEmpty()) {
            DB::table('ventas')->whereIn('id', $ventaIds)->delete();
        }

        if ($noteIds->isNotEmpty()) {
            DB::table('notes')->whereIn('id', $noteIds)->delete();
        }

        if ($customerIds->isNotEmpty()) {
            DB::table('customers')->whereIn('id', $customerIds)->delete();
        }

        if (Schema::hasTable('contratos_mes_variacion_items')) {
            DB::table('contratos_mes_variacion_items')
                ->where('nro_contr_adm', 'like', 'DEMOLC%')
                ->delete();
        }

        // Reinicia baselines de los meses que toca el demo (para variación limpia)
        $demoMonths = [
            now()->format('Y-m'),
            now()->subMonth()->format('Y-m'),
            now()->subMonths(2)->format('Y-m'),
            now()->subMonths(3)->format('Y-m'),
            now()->subMonths(5)->format('Y-m'),
            now()->subMonths(8)->format('Y-m'),
        ];

        ContratoMesBaseline::query()
            ->whereIn('mes_key', $demoMonths)
            ->delete();
    }

    /**
     * @return int número de contratos creados en la fase base
     */
    protected function seedDemoContratos(User $actor, User $comercial): int
    {
        // Meses con suficientes contratos para poder soft-delete / añadir después
        $plan = [
            0 => 4,  // mes actual → luego +2 = variación +2
            1 => 4,  // mes -1 → luego soft-delete 2 = variación -2
            2 => 3,  // mes -2 → sin tocar (0)
            3 => 3,  // mes -3 → sin tocar (0)
            5 => 2,  // relleno
            8 => 2,  // relleno
        ];

        $seq = 1;
        $noteSeq = 99601;

        foreach ($plan as $monthsAgo => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->createDemoVenta($actor, $comercial, $seq, $noteSeq, $monthsAgo, $i);
                $seq++;
                $noteSeq++;
            }
        }

        return $seq - 1;
    }

    /**
     * 1) Fija baseline con los totales actuales
     * 2) Soft-delete 2 del mes -1 → VARIACIÓN negativa
     * 3) Crea 2 extra en mes actual → VARIACIÓN positiva
     * 4) Mes -2 / -3 quedan en 0
     */
    protected function applyVariacionExamples(User $actor, User $comercial): void
    {
        ContratosPorMesStats::freezeBaselinesToCurrent();

        $mesMenos = now()->subMonth()->format('Y-m');

        $toSoftDelete = Venta::query()
            ->where('nro_contr_adm', 'like', 'DEMOLC%')
            ->whereRaw("DATE_FORMAT(fecha_venta, '%Y-%m') = ?", [$mesMenos])
            ->orderBy('id')
            ->limit(2)
            ->get();

        foreach ($toSoftDelete as $venta) {
            VentaSoftDelete::delete($venta, $actor->id);
            // Marca descripción si existe
            if (Schema::hasColumn('ventas', 'list_descripcion')) {
                Venta::withTrashed()->whereKey($venta->id)->update([
                    'list_descripcion' => 'Demo VARIACIÓN-: soft-deleted a propósito',
                ]);
            }
        }

        // +2 en mes actual (después de fijar la base)
        $seq = (int) Venta::withTrashed()
            ->where('nro_contr_adm', 'like', 'DEMOLC%')
            ->count() + 1;
        $noteSeq = 99680;

        for ($i = 0; $i < 2; $i++) {
            $venta = $this->createDemoVenta($actor, $comercial, $seq, $noteSeq, 0, 20 + $i, extraMas: true);
            // Uno con empleado, otro como sistema (sin usuario)
            ContratosPorMesStats::recordVariationItem(
                $venta,
                ContratoMesVariacionItem::ESTADO_NUEVO,
                $i === 0 ? $comercial->id : null,
            );
            $seq++;
            $noteSeq++;
        }

        $detalleCount = ContratoMesVariacionItem::query()
            ->where('nro_contr_adm', 'like', 'DEMOLC%')
            ->count();

        // Garantiza al menos 4 filas de detalle visibles en local
        if ($detalleCount < 4) {
            $this->command?->warn("Detalle de variaciones: solo {$detalleCount} filas (se esperaban 4).");
        }
    }

    protected function createDemoVenta(
        User $actor,
        User $comercial,
        int $seq,
        int $noteSeq,
        int $monthsAgo,
        int $dayOffset,
        bool $extraMas = false,
    ): Venta {
        $nro = 'DEMOLC' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
        $dni = 'DEMOLC' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
        $day = min(28, 3 + ($dayOffset * 2));

        $fecha = now()
            ->startOfMonth()
            ->subMonths($monthsAgo)
            ->day($day)
            ->setTime(10 + ($dayOffset % 8), 15);

        $customer = Customer::query()->create([
            'first_names' => $extraMas ? 'Cliente Extra' : 'Cliente Demo',
            'last_names' => ($extraMas ? 'Mas ' : 'List ') . $seq,
            'phone' => '64000' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'dni' => $dni,
            'postal_code' => '36' . str_pad((string) ($seq % 1000), 3, '0', STR_PAD_LEFT),
            'ciudad' => 'Vigo',
            'provincia' => 'Pontevedra',
            'primary_address' => 'Rua Demo ListContract ' . $seq,
        ]);

        $note = Note::query()->create([
            'nro_nota' => (string) $noteSeq,
            'user_id' => $actor->id,
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
            'fuente' => FuenteNotas::CALLE->value,
            'status' => NoteStatus::CONTACTED->value,
            'estado_terminal' => EstadoTerminal::VENTA->value,
            'assignment_date' => $fecha->copy()->subDays(2),
            'visit_date' => $fecha->copy()->subDay(),
            'visit_schedule' => 'TD',
            'show_phone' => true,
        ]);

        $payload = [
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
            'fecha_venta' => $fecha,
            'importe_total' => 800 + ($seq * 25),
            'importe_comercial' => 800 + ($seq * 25),
            'modalidad_pago' => $seq % 2 === 0 ? 'Financiado' : 'Contado',
            'num_cuotas' => $seq % 2 === 0 ? 12 : 1,
            'nro_contr_adm' => $nro,
            'nro_cliente_adm' => '88' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'origen_venta' => OrigenVenta::VENTA_NORMAL->value,
            'estado_venta' => EstadoVenta::EN_REVISION->value,
        ];

        if (Schema::hasColumn('ventas', 'en_app')) {
            $payload['en_app'] = $seq % 3 === 0;
        }

        if (Schema::hasColumn('ventas', 'list_descripcion')) {
            if ($extraMas) {
                $payload['list_descripcion'] = "Demo VARIACIÓN+: añadido tras fijar base ({$nro})";
            } elseif ($seq % 3 === 0) {
                $payload['list_descripcion'] = "Demo ListContract {$nro}: verificado en app (prueba).";
            }
        }

        return Venta::query()->create($payload);
    }
}
