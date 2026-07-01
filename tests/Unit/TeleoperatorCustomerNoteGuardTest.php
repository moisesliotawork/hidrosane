<?php

namespace Tests\Unit;

use App\Enums\EstadoTerminal;
use App\Models\Customer;
use App\Models\Note;
use App\Support\TeleoperatorCustomerNoteGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeleoperatorCustomerNoteGuardTest extends TestCase
{
    use DatabaseTransactions;

    private TeleoperatorCustomerNoteGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = app(TeleoperatorCustomerNoteGuard::class);
    }

    public function test_blocks_when_duplicate_customers_share_secondary_phone_and_one_has_recent_confirmed_note(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $sharedSecondary = '663576632';

        $customerWithoutNote = Customer::factory()->create([
            'phone' => '981229229',
            'secondary_phone' => $sharedSecondary,
        ]);

        $customerWithNote = Customer::factory()->create([
            'phone' => '981229226',
            'secondary_phone' => $sharedSecondary,
        ]);

        Note::factory()->create([
            'customer_id' => $customerWithNote->id,
            'estado_terminal' => EstadoTerminal::CONFIRMADO,
            'visit_date' => '2026-06-29',
            'assignment_date' => '2026-06-29',
            'printed' => false,
        ]);

        $evaluation = $this->guard->evaluateForPhone('981229229');

        $this->assertFalse($evaluation->allowed);
        $this->assertSame('blocked', $evaluation->outcome);
        $this->assertStringContainsString('duplicado', strtolower($evaluation->message));
    }

    public function test_expands_customers_by_shared_phones(): void
    {
        $sharedSecondary = '612345678';

        $first = Customer::factory()->create([
            'phone' => '611111111',
            'secondary_phone' => $sharedSecondary,
        ]);

        $second = Customer::factory()->create([
            'phone' => '622222222',
            'secondary_phone' => $sharedSecondary,
        ]);

        $resolved = $this->guard->resolveCustomersForPhone('611111111');

        $this->assertCount(2, $resolved);
        $this->assertTrue($resolved->pluck('id')->contains($first->id));
        $this->assertTrue($resolved->pluck('id')->contains($second->id));
    }

    public function test_allows_when_all_notes_are_older_than_cutoff(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $customer = Customer::factory()->create([
            'phone' => '633333333',
        ]);

        Note::factory()->create([
            'customer_id' => $customer->id,
            'estado_terminal' => EstadoTerminal::CONFIRMADO,
            'visit_date' => '2025-12-15',
            'assignment_date' => '2025-12-15',
        ]);

        $evaluation = $this->guard->evaluateForPhone('633333333');

        $this->assertTrue($evaluation->allowed);
        $this->assertSame('allowed_old', $evaluation->outcome);
    }

    public function test_normalizes_phone_with_spaces_for_search(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '644444444',
        ]);

        $found = $this->guard->findCustomersByPhone('644 444 444');

        $this->assertCount(1, $found);
        $this->assertSame($customer->id, $found->first()->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
