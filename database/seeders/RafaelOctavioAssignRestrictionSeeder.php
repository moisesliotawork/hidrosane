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
use Illuminate\Database\Seeder;

/**
 * Cliente de prueba para restricción de asignación (venta reciente) en Jefe de Sala.
 *
 * php artisan db:seed --class=RafaelOctavioAssignRestrictionSeeder
 */
class RafaelOctavioAssignRestrictionSeeder extends Seeder
{
    public const PHONE = '605016975';

    public const DNI = 'SEEDRAF001';

    public const NRO_NOTA_VENTA = '99001';

    public const NRO_NOTA_ASIGNAR = '99002';

    public const NRO_CONTRATO = '99001';

    public function run(): void
    {
        $comercial = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
            ->whereNull('baja')
            ->orderBy('id')
            ->first();

        $autor = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'head_of_room'))
            ->whereNull('baja')
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if (! $comercial || ! $autor) {
            $this->command?->error('Faltan usuarios comercial / autor en BD.');

            return;
        }

        $customer = Customer::query()->updateOrCreate(
            ['dni' => self::DNI],
            [
                'first_names' => 'Rafael',
                'last_names' => 'Octavio',
                'phone' => self::PHONE,
                'secondary_phone' => null,
                'phone1_commercial' => self::PHONE,
                'phone2_commercial' => null,
                'primary_address' => 'Calle Prueba Restricción 1',
                'postal_code' => '36201',
                'ciudad' => 'Vigo',
                'provincia' => 'Pontevedra',
                'email' => 'rafael.octavio.seed@test.local',
                'inhabilitado' => false,
            ],
        );

        $fechaVenta = now()->subMonth()->startOfDay()->setTime(12, 0);

        $noteVenta = Note::query()->updateOrCreate(
            ['nro_nota' => self::NRO_NOTA_VENTA],
            [
                'user_id' => $autor->id,
                'customer_id' => $customer->id,
                'comercial_id' => $comercial->id,
                'fuente' => FuenteNotas::CALLE->value,
                'status' => NoteStatus::CONTACTED->value,
                'estado_terminal' => EstadoTerminal::VENTA,
                'assignment_date' => $fechaVenta->copy()->subDay(),
                'visit_date' => $fechaVenta,
                'reten' => false,
                'printed' => false,
            ],
        );

        $venta = Venta::query()->updateOrCreate(
            ['nro_contr_adm' => self::NRO_CONTRATO],
            [
                'note_id' => $noteVenta->id,
                'customer_id' => $customer->id,
                'comercial_id' => $comercial->id,
                'nro_cliente_adm' => '99001',
                'fecha_venta' => $fechaVenta,
                'importe_total' => 3764.00,
                'importe_comercial' => 3764.00,
                'modalidad_pago' => 'Financiado',
                'forma_pago' => 'Transferencia',
                'num_cuotas' => 39,
                'cuota_mensual' => 96.51,
                'origen_venta' => OrigenVenta::VENTA_NORMAL,
                'mes_contr' => $fechaVenta->translatedFormat('F'),
                'motivo_venta' => 'Seeder prueba restricción asignación',
                'status' => 'BORRADOR',
            ],
        );

        // Nota lista para asignar en Jefe de Sala (sin comercial)
        $noteAsignar = Note::query()->updateOrCreate(
            ['nro_nota' => self::NRO_NOTA_ASIGNAR],
            [
                'user_id' => $autor->id,
                'customer_id' => $customer->id,
                'comercial_id' => null,
                'assignment_date' => null,
                'fuente' => FuenteNotas::CALLE->value,
                'status' => NoteStatus::CONTACTED->value,
                'estado_terminal' => EstadoTerminal::SIN_ESTADO,
                'reten' => false,
                'printed' => false,
            ],
        );

        $this->command?->info('Seeder Rafael Octavio listo para probar restricción de asignación.');
        $this->command?->line("  Cliente #{$customer->id}: Rafael Octavio");
        $this->command?->line('  Teléfono: '.self::PHONE);
        $this->command?->line("  Venta #{$venta->id} ({$venta->nro_contr_adm}) fecha: {$fechaVenta->format('d/m/Y H:i')}");
        $this->command?->line("  Nota con venta: #{$noteVenta->nro_nota}");
        $this->command?->line("  Nota para ASIGNAR (sin comercial): #{$noteAsignar->nro_nota} (id {$noteAsignar->id})");
        $this->command?->line("  Comercial de la venta: {$comercial->empleado_id} {$comercial->name}");
        $this->command?->newLine();
        $this->command?->comment('En Jefe de Sala → Notas, busca nota '.self::NRO_NOTA_ASIGNAR.' e intenta asignar un comercial.');
        $this->command?->comment('Debería abrir el modal de restricción con ASIGNAR DE TODOS MODOS.');
    }
}
