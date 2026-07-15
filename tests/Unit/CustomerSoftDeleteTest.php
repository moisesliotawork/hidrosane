<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\CustomerSoftDelete;
use App\Support\CustomerSoftRestore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CustomerSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_archives_customer_and_records_deleted_by_user(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create([
            'dni' => '35244615J',
        ]);

        $this->actingAs($user);

        CustomerSoftDelete::delete($customer);

        $this->assertNull(Customer::find($customer->id));

        $archived = Customer::onlyTrashed()->find($customer->id);

        $this->assertNotNull($archived);
        $this->assertNotNull($archived->deleted_at);
        $this->assertSame($user->id, $archived->deleted_by_user_id);
        $this->assertSame('35244615J', $archived->dni);
    }

    public function test_restore_brings_back_archived_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user);
        CustomerSoftDelete::delete($customer);

        $archived = Customer::onlyTrashed()->findOrFail($customer->id);
        CustomerSoftRestore::restore($archived);

        $restored = Customer::find($customer->id);

        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertNull($restored->deleted_by_user_id);
    }

    public function test_soft_delete_keeps_notes_and_ventas(): void
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
            'importe_total' => 1899,
            'importe_comercial' => 1899,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 39,
            'nro_contr_adm' => '01299',
            'origen_venta' => 'venta_normal',
        ]);

        $this->actingAs($user);
        CustomerSoftDelete::delete($customer);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'customer_id' => $customer->id,
            'nro_contr_adm' => '01299',
        ]);
        $this->assertNull(Venta::find($venta->id)?->deleted_at);
    }

    public function test_force_delete_is_blocked(): void
    {
        $customer = Customer::factory()->create();

        $this->expectException(RuntimeException::class);

        $customer->forceDelete();
    }
}
