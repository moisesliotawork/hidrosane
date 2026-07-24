<?php

namespace Tests\Unit;

use App\Enums\OrigenVenta;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\HeadOfRoom\NoteAssignRestriction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ejecutar:
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Unit/NoteAssignRestrictionTest.php
 */
class NoteAssignRestrictionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detects_recent_sale_restriction_for_note(): void
    {
        $comercial = User::query()->whereNotNull('empleado_id')->first()
            ?? User::factory()->create([
                'name' => 'Comercial',
                'last_name' => 'Test',
                'empleado_id' => 'T'.random_int(1000, 9999),
                'email' => 'hor-restriccion-'.Str::random(6).'@test.local',
            ]);

        $phone = '611'.random_int(100000, 999999);

        $customer = Customer::factory()->create([
            'first_names' => 'Cliente',
            'last_names' => 'Restriccion '.Str::random(4),
            'phone' => $phone,
            'phone1_commercial' => $phone,
        ]);

        $noteBase = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
        ]);

        Venta::create([
            'note_id' => $noteBase->id,
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
            'fecha_venta' => now()->subMonth(),
            'importe_total' => 1000,
            'importe_comercial' => 1000,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => OrigenVenta::VENTA_NORMAL,
        ]);

        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => null,
        ]);

        $restriction = NoteAssignRestriction::forNote($note->fresh(['customer']));

        $this->assertNotNull($restriction);
        $this->assertSame('venta_reciente', $restriction['code']);
        $this->assertStringContainsString('venta reciente', mb_strtolower($restriction['message']));
    }

    public function test_allows_assign_when_no_recent_sale(): void
    {
        $phone = '622'.random_int(100000, 999999);

        $customer = Customer::factory()->create([
            'first_names' => 'Libre',
            'last_names' => 'Sin Venta '.Str::random(4),
            'phone' => $phone,
            'phone1_commercial' => $phone,
        ]);

        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => null,
        ]);

        $this->assertNull(NoteAssignRestriction::forNote($note->fresh(['customer'])));
    }

    public function test_modal_content_includes_restriction_details(): void
    {
        $html = NoteAssignRestriction::singleModalContent([
            'code' => 'venta_reciente',
            'title' => 'Asignación restringida',
            'message' => 'NO PUEDES REASIGNAR AL CLIENTE: TEST',
            'customer_name' => 'TEST CLIENTE',
            'fecha_venta' => '01/11/2025 10:00',
            'comercial_emp' => '007',
            'nro_nota' => 1234,
        ])->toHtml();

        $this->assertStringContainsString('TEST CLIENTE', $html);
        $this->assertStringContainsString('01/11/2025 10:00', $html);
        $this->assertStringContainsString('#1234', $html);
        $this->assertStringContainsString('Venta reciente', $html);
        $this->assertStringContainsString('hor-restriccion-venta-reciente', $html);
        $this->assertStringContainsString('hor-restriccion-parpadeo', $html);
    }
}
