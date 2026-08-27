<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\VentaReserva;
use App\Support\VentaSoftDelete;
use App\Support\VentaSoftRestore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class VentaSoftDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_soft_delete_archives_venta_and_records_deleted_by_user(): void
    {
        [$user, $venta] = $this->makeVenta();
        $this->actingAs($user);

        VentaSoftDelete::delete($venta);

        $this->assertNull(Venta::find($venta->id));

        $archived = Venta::onlyTrashed()->find($venta->id);

        $this->assertNotNull($archived);
        $this->assertNotNull($archived->deleted_at);
        $this->assertSame($user->id, $archived->deleted_by_user_id);
        $this->assertSame($venta->nro_contr_adm, $archived->nro_contr_adm);
    }

    public function test_restore_brings_back_archived_venta(): void
    {
        [$user, $venta] = $this->makeVenta();
        $this->actingAs($user);
        VentaSoftDelete::delete($venta);

        $archived = Venta::onlyTrashed()->findOrFail($venta->id);
        VentaSoftRestore::restore($archived);

        $restored = Venta::find($venta->id);

        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertNull($restored->deleted_by_user_id);
        $this->assertNull($restored->reservado_at);
        $this->assertNull($restored->reservado_by_user_id);
    }

    public function test_move_to_reserva_leaves_contract_recoverable_and_out_of_borrados(): void
    {
        [$user, $venta] = $this->makeVenta();
        $this->actingAs($user);
        VentaSoftDelete::delete($venta);

        VentaReserva::move($venta->fresh() ?? Venta::onlyTrashed()->findOrFail($venta->id), $user->id);

        $this->assertNull(Venta::find($venta->id));
        $this->assertNull(Venta::onlyTrashed()->enContratosBorrados()->find($venta->id));

        $reservado = Venta::onlyTrashed()->enReserva()->find($venta->id);
        $this->assertNotNull($reservado);
        $this->assertNotNull($reservado->reservado_at);
        $this->assertSame($user->id, $reservado->reservado_by_user_id);
        $this->assertSame($venta->nro_contr_adm, $reservado->nro_contr_adm);
    }

    public function test_restore_from_reserva_returns_to_contratos(): void
    {
        [$user, $venta] = $this->makeVenta();
        $this->actingAs($user);
        VentaSoftDelete::delete($venta);
        VentaReserva::move(Venta::onlyTrashed()->findOrFail($venta->id), $user->id);

        VentaSoftRestore::restore(Venta::onlyTrashed()->findOrFail($venta->id));

        $restored = Venta::find($venta->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertNull($restored->reservado_at);
        $this->assertNull($restored->reservado_by_user_id);
    }

    public function test_move_all_from_borrados_clears_borrados_into_reserva(): void
    {
        [$user, $ventaA] = $this->makeVenta();
        [, $ventaB] = $this->makeVenta(user: $user);
        $this->actingAs($user);

        $borradosAntes = Venta::onlyTrashed()->enContratosBorrados()->count();
        VentaSoftDelete::delete($ventaA);
        VentaSoftDelete::delete($ventaB);

        $moved = VentaReserva::moveAllFromBorrados($user->id);

        $this->assertSame($borradosAntes + 2, $moved);
        $this->assertSame(0, Venta::onlyTrashed()->enContratosBorrados()->count());
        $this->assertNotNull(Venta::onlyTrashed()->enReserva()->find($ventaA->id));
        $this->assertNotNull(Venta::onlyTrashed()->enReserva()->find($ventaB->id));
    }

    public function test_force_delete_throws_and_keeps_the_contract(): void
    {
        [$user, $venta] = $this->makeVenta();
        $this->actingAs($user);
        VentaSoftDelete::delete($venta);

        $archived = Venta::onlyTrashed()->findOrFail($venta->id);

        try {
            $archived->forceDelete();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bloqueado', $e->getMessage());
        }

        $this->assertNotNull(Venta::onlyTrashed()->find($venta->id));
    }

    /**
     * @return array{0: User, 1: Venta}
     */
    private function makeVenta(?string $nro = null, ?User $user = null): array
    {
        $user ??= User::factory()->create([
            'name' => 'Test',
            'last_name' => 'Reserva',
            'email' => 'reserva-'.Str::random(8).'@test.local',
            'empleado_id' => (string) random_int(100, 999),
        ]);
        $customer = Customer::factory()->create([
            'dni' => 'T'.Str::upper(Str::random(8)),
        ]);
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 1899,
            'importe_comercial' => 1899,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 39,
            'nro_contr_adm' => $nro ?? ('T'.random_int(10000, 99999)),
            'nro_cliente_adm' => 'T'.random_int(1000, 9999),
            'origen_venta' => 'venta_normal',
        ]);

        return [$user, $venta];
    }
}
