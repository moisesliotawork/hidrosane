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
        [$user, $customer, $note, $ventas] = $this->seedNoteWithVentas(1);

        $this->actingAs($user);
        $venta = $ventas[0];
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

    public function test_deleting_note_does_not_soft_delete_multiple_linked_ventas(): void
    {
        [$user, $customer, $note, $ventas] = $this->seedNoteWithVentas(3);

        $this->actingAs($user);
        $noteId = $note->id;
        $ventaIds = array_map(fn (Venta $v) => $v->id, $ventas);
        $nros = array_map(fn (Venta $v) => $v->nro_contr_adm, $ventas);

        $note->delete();

        $this->assertNull(Note::find($noteId));
        $this->assertNotNull(Note::onlyTrashed()->find($noteId));

        foreach ($ventaIds as $i => $ventaId) {
            $alive = Venta::find($ventaId);
            $this->assertNotNull($alive, "Contrato {$nros[$i]} no debe borrarse al borrar la nota");
            $this->assertNull($alive->deleted_at);
            $this->assertSame($nros[$i], $alive->nro_contr_adm);
            $this->assertSame($noteId, $alive->note_id);
            $this->assertNull(Venta::onlyTrashed()->find($ventaId));
        }

        $this->assertSame(0, Venta::onlyTrashed()->whereIn('id', $ventaIds)->count());
        $this->assertSame(3, Venta::whereIn('id', $ventaIds)->count());
    }

    public function test_deleting_several_notes_does_not_touch_their_ventas(): void
    {
        $user = User::factory()->create([
            'name' => 'Test',
            'last_name' => 'MultiNote',
            'email' => 'multi-note-'.Str::random(6).'@test.local',
            'empleado_id' => (string) random_int(100, 999),
        ]);
        $this->actingAs($user);

        $pairs = [];
        for ($i = 0; $i < 3; $i++) {
            $customer = Customer::factory()->create([
                'dni' => 'T'.Str::upper(Str::random(8)),
            ]);
            $note = Note::factory()->create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
            ]);
            $venta = $this->createVentaFor($note, $customer, $user);
            $pairs[] = [$note, $venta];
        }

        foreach ($pairs as [$note]) {
            $note->delete();
        }

        foreach ($pairs as [$note, $venta]) {
            $this->assertNotNull(Note::onlyTrashed()->find($note->id));
            $alive = Venta::find($venta->id);
            $this->assertNotNull($alive);
            $this->assertNull($alive->deleted_at);
            $this->assertSame($note->id, $alive->note_id);
        }
    }

    /**
     * @return array{0: User, 1: Customer, 2: Note, 3: list<Venta>}
     */
    private function seedNoteWithVentas(int $ventaCount): array
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

        $ventas = [];
        for ($i = 0; $i < $ventaCount; $i++) {
            $ventas[] = $this->createVentaFor($note, $customer, $user);
        }

        return [$user, $customer, $note, $ventas];
    }

    private function createVentaFor(Note $note, Customer $customer, User $user): Venta
    {
        return Venta::create([
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
