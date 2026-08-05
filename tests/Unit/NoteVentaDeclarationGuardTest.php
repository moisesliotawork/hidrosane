<?php

namespace Tests\Unit;

use App\Enums\EstadoTerminal;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\NoteVentaDeclarationGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoteVentaDeclarationGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_terminal_venta_always_blocked_with_message(): void
    {
        $note = new Note(['nro_nota' => '14810']);
        $message = NoteVentaDeclarationGuard::blockReasonForManualTerminalVenta($note);

        $this->assertStringContainsString('14810', $message);
        $this->assertStringContainsString('falsa declaración', $message);
        $this->assertStringContainsString('Puerta Fría', $message);
    }

    public function test_would_become_venta(): void
    {
        $this->assertTrue(NoteVentaDeclarationGuard::wouldBecomeVenta(EstadoTerminal::VENTA));
        $this->assertTrue(NoteVentaDeclarationGuard::wouldBecomeVenta('venta'));
        $this->assertTrue(NoteVentaDeclarationGuard::wouldBecomeVenta(EstadoTerminal::nextFromRaw('nulo')));
        $this->assertFalse(NoteVentaDeclarationGuard::wouldBecomeVenta(EstadoTerminal::CONFIRMADO));
        $this->assertFalse(NoteVentaDeclarationGuard::wouldBecomeVenta(''));
    }

    public function test_start_from_note_allowed_without_contracts(): void
    {
        $user = User::factory()->create([
            'name' => 'Guard',
            'last_name' => 'Test',
            'email' => 'guard-'.Str::random(6).'@test.local',
            'empleado_id' => (string) random_int(100, 999),
        ]);
        $customer = Customer::factory()->create();
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'nro_nota' => (string) random_int(20000, 29999),
        ]);

        $this->assertNull(NoteVentaDeclarationGuard::blockReasonForStartingVentaFromNote($note));
    }

    public function test_start_from_note_blocked_when_active_contracts_exist(): void
    {
        $user = User::factory()->create([
            'name' => 'Guard',
            'last_name' => 'Test2',
            'email' => 'guard2-'.Str::random(6).'@test.local',
            'empleado_id' => (string) random_int(100, 999),
        ]);
        $customer = Customer::factory()->create();
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'nro_nota' => '14810',
        ]);

        Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 100,
            'importe_comercial' => 100,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'nro_contr_adm' => '673',
            'origen_venta' => 'venta_normal',
        ]);

        $message = NoteVentaDeclarationGuard::blockReasonForStartingVentaFromNote($note);

        $this->assertNotNull($message);
        $this->assertStringContainsString('14810', $message);
        $this->assertStringContainsString('673', $message);
        $this->assertStringContainsString('Puerta Fría', $message);
    }
}
