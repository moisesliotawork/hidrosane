<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerMergeService;
use App\Support\Filament\FechaNacimientoField;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerMergeFechaNacTest extends TestCase
{
    use DatabaseTransactions;

    public function test_merge_by_ids_unifies_conflicting_fecha_nac_for_same_dni(): void
    {
        $user = User::query()->firstOrFail();

        $keeper = Customer::factory()->create([
            'dni' => '32416659E',
            'fecha_nac' => '1948-12-07',
            'first_names' => 'MARIA',
            'last_names' => 'OLIVA',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $toDelete = Customer::factory()->create([
            'dni' => '32416659e',
            'fecha_nac' => '1948-12-08',
            'first_names' => 'MARIA',
            'last_names' => 'OLIVA LAMAS',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        app(CustomerMergeService::class)->mergeByIds($keeper->id, $toDelete->id, $user->id);

        $keeper->refresh();

        $this->assertSame('1948-12-08', $keeper->getRawOriginal('fecha_nac'));
    }

    public function test_merge_by_ids_keeps_keeper_fecha_nac_when_it_was_updated_more_recently(): void
    {
        $user = User::query()->firstOrFail();

        $keeper = Customer::factory()->create([
            'dni' => '77777777H',
            'fecha_nac' => '1948-12-08',
            'updated_at' => now(),
        ]);

        $toDelete = Customer::factory()->create([
            'dni' => '77777777H',
            'fecha_nac' => '1948-12-07',
            'updated_at' => now()->subDay(),
        ]);

        app(CustomerMergeService::class)->mergeByIds($keeper->id, $toDelete->id, $user->id);

        $keeper->refresh();

        $this->assertSame('1948-12-08', $keeper->getRawOriginal('fecha_nac'));
    }

    public function test_resolve_on_customer_merge_does_not_overwrite_when_dni_differs(): void
    {
        $keeper = Customer::factory()->make([
            'dni' => '11111111A',
            'fecha_nac' => '1948-12-07',
            'updated_at' => now()->subDay(),
        ]);

        $toDelete = Customer::factory()->make([
            'dni' => '22222222B',
            'fecha_nac' => '1948-12-08',
            'updated_at' => now(),
        ]);

        $this->assertSame(
            '1948-12-07',
            FechaNacimientoField::resolveOnCustomerMerge($keeper, $toDelete),
        );
    }
}
