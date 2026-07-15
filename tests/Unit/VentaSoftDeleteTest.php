<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\VentaSoftDelete;
use App\Support\VentaSoftRestore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_archives_venta_and_records_deleted_by_user(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create([
            'dni' => '35244615J',
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
            'nro_contr_adm' => '01299',
            'nro_cliente_adm' => '00727',
            'origen_venta' => 'venta_normal',
        ]);

        $this->actingAs($user);

        VentaSoftDelete::delete($venta);

        $this->assertNull(Venta::find($venta->id));

        $archived = Venta::onlyTrashed()->find($venta->id);

        $this->assertNotNull($archived);
        $this->assertNotNull($archived->deleted_at);
        $this->assertSame($user->id, $archived->deleted_by_user_id);
        $this->assertSame('01299', $archived->nro_contr_adm);
    }

    public function test_restore_brings_back_archived_venta(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 500,
            'importe_comercial' => 500,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'venta_normal',
        ]);

        $this->actingAs($user);
        VentaSoftDelete::delete($venta);

        $archived = Venta::onlyTrashed()->findOrFail($venta->id);
        VentaSoftRestore::restore($archived);

        $restored = Venta::find($venta->id);

        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertNull($restored->deleted_by_user_id);
    }
}
