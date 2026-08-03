<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\VentaSoftDelete;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ejecutar:
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Unit/NoteDeleteProtectsVentaTest.php
 */
class NoteDeleteProtectsVentaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deleting_note_does_not_soft_delete_venta(): void
    {
        $user = User::factory()->create([
            'name' => 'Test',
            'last_name' => 'NoteDel',
            'email' => 'note-del-'.Str::random(6).'@test.local',
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
            'importe_total' => 1000,
            'importe_comercial' => 1000,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'nro_contr_adm' => 'T'.random_int(1000, 9999),
            'origen_venta' => 'venta_normal',
        ]);

        $this->actingAs($user);
        $ventaId = $venta->id;
        $nro = $venta->nro_contr_adm;
        $noteId = $note->id;

        $note->delete();

        $this->assertNull(Note::find($noteId));
        $archivedNote = Note::onlyTrashed()->find($noteId);
        $this->assertNotNull($archivedNote);
        $this->assertNotNull($archivedNote->deleted_at);
        $this->assertSame($user->id, $archivedNote->deleted_by_user_id);

        $alive = Venta::find($ventaId);
        $this->assertNotNull($alive);
        $this->assertNull($alive->deleted_at);
        $this->assertSame($nro, $alive->nro_contr_adm);
        $this->assertSame($noteId, $alive->note_id);
        $this->assertNull(Venta::onlyTrashed()->find($ventaId));
    }

    public function test_plain_venta_delete_is_soft_and_sets_deleted_by_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Test',
            'last_name' => 'VentaDel',
            'email' => 'venta-del-'.Str::random(6).'@test.local',
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
            'importe_total' => 500,
            'importe_comercial' => 500,
            'modalidad_pago' => 'Contado',
            'num_cuotas' => 1,
            'nro_contr_adm' => 'T'.random_int(1000, 9999),
            'origen_venta' => 'venta_normal',
        ]);

        $this->actingAs($user);
        $venta->delete();

        $this->assertNull(Venta::find($venta->id));
        $archived = Venta::onlyTrashed()->find($venta->id);
        $this->assertNotNull($archived);
        $this->assertSame($user->id, $archived->deleted_by_user_id);
    }

    public function test_force_delete_is_blocked(): void
    {
        $user = User::factory()->create([
            'name' => 'Test',
            'last_name' => 'VentaForce',
            'email' => 'venta-force-'.Str::random(6).'@test.local',
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
            'importe_total' => 500,
            'importe_comercial' => 500,
            'modalidad_pago' => 'Contado',
            'num_cuotas' => 1,
            'nro_contr_adm' => 'T'.random_int(1000, 9999),
            'origen_venta' => 'venta_normal',
        ]);

        $this->actingAs($user);
        VentaSoftDelete::delete($venta);

        $archived = Venta::onlyTrashed()->findOrFail($venta->id);
        $archived->forceDelete();

        $this->assertNotNull(Venta::onlyTrashed()->find($venta->id));
    }
}
