<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Scopes\NotMergedScope;
use App\Models\User;
use App\Models\Venta;
use App\Support\PuertaFriaCustomerResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PuertaFriaCustomerResolverTest extends TestCase
{
    use DatabaseTransactions;

    private PuertaFriaCustomerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(PuertaFriaCustomerResolver::class);
    }

    public function test_reuses_existing_customer_by_phone_with_formatted_spaces(): void
    {
        $existing = Customer::factory()->create([
            'first_names' => 'JUAN',
            'last_names' => 'PEREZ LOPEZ',
            'phone1_commercial' => '666111222',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'JUAN',
            'last_names' => 'PEREZ LOPEZ',
            'phone1_commercial' => '666 111 222',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertSame($existing->id, $resolved->id);
    }

    public function test_reuses_existing_customer_by_name_when_phone_is_new(): void
    {
        $existing = Customer::factory()->create([
            'first_names' => 'MARIA',
            'last_names' => 'GARCIA RUIZ',
            'phone1_commercial' => '611222333',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'MARIA',
            'last_names' => 'GARCIA RUIZ',
            'phone1_commercial' => '699888777',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertSame($existing->id, $resolved->id);
    }

    public function test_merges_duplicate_customers_with_same_phone_and_name(): void
    {
        $user = User::query()->firstOrFail();

        $older = Customer::factory()->create([
            'first_names' => 'PEDRO',
            'last_names' => 'SANCHEZ',
            'phone1_commercial' => '622333444',
            'created_at' => now()->subDays(3),
        ]);

        $newer = Customer::factory()->create([
            'first_names' => 'PEDRO',
            'last_names' => 'SANCHEZ',
            'phone' => '622333444',
            'created_at' => now()->subDay(),
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'PEDRO',
            'last_names' => 'SANCHEZ',
            'phone1_commercial' => '622 333 444',
            'pf_existing_customer_id' => null,
        ], $user->id);

        $this->assertSame($older->id, $resolved->id);

        $newer->refresh();
        $this->assertSame($older->id, $newer->merged_into_id);

        $activeCount = Customer::query()
            ->where('first_names', 'PEDRO')
            ->where('last_names', 'SANCHEZ')
            ->count();

        $this->assertSame(1, $activeCount);
    }

    public function test_selected_existing_customer_merges_only_same_phone_and_name_duplicates(): void
    {
        $user = User::query()->firstOrFail();

        $keeper = Customer::factory()->create([
            'first_names' => 'LUIS',
            'last_names' => 'MARTIN',
            'phone1_commercial' => '633444555',
            'created_at' => now()->subDays(2),
        ]);

        $duplicate = Customer::factory()->create([
            'first_names' => 'LUIS',
            'last_names' => 'MARTIN',
            'secondary_phone' => '633444555',
            'created_at' => now()->subDay(),
        ]);

        $otherNameSamePhone = Customer::factory()->create([
            'first_names' => 'CARLA',
            'last_names' => 'RODRIGUEZ',
            'phone1_commercial' => '633444555',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'LUIS',
            'last_names' => 'MARTIN',
            'phone1_commercial' => '633444555',
            'pf_existing_customer_id' => $keeper->id,
        ], $user->id);

        $this->assertSame($keeper->id, $resolved->id);

        $duplicate->refresh();
        $otherNameSamePhone->refresh();

        $this->assertSame($keeper->id, $duplicate->merged_into_id);
        $this->assertNull($otherNameSamePhone->merged_into_id);
    }

    public function test_creates_new_customer_when_phone_exists_but_name_differs(): void
    {
        $existing = Customer::factory()->create([
            'first_names' => 'JUAN',
            'last_names' => 'PEREZ LOPEZ',
            'phone1_commercial' => '677888999',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'ZZZUNIQUE',
            'last_names' => 'CLIENTE NUEVO',
            'phone1_commercial' => '677 888 999',
            'primary_address' => 'Calle Test 2',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertNotSame($existing->id, $resolved->id);
        $this->assertSame('677888999', $resolved->phone1_commercial);
        $this->assertNull($resolved->merged_into_id);

        $this->assertSame(
            2,
            Customer::query()->where('phone1_commercial', '677888999')->count(),
        );
    }

    public function test_creates_new_customer_when_similar_name_but_different_dni(): void
    {
        $existing = Customer::factory()->create([
            'first_names' => 'CARLOS',
            'last_names' => 'RAMIREZ',
            'phone1_commercial' => '688999000',
            'dni' => '11111111A',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'CARLOS',
            'last_names' => 'RAMIREZ',
            'phone1_commercial' => '688111222',
            'dni' => '22222222B',
            'primary_address' => 'Calle Test 3',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertNotSame($existing->id, $resolved->id);
        $this->assertSame('22222222B', $resolved->dni);
        $this->assertNull($resolved->merged_into_id);
    }

    public function test_reuses_and_merges_by_same_dni_even_with_different_names(): void
    {
        $user = User::query()->firstOrFail();

        $older = Customer::factory()->create([
            'first_names' => 'TOMAS',
            'last_names' => 'VEGA',
            'phone1_commercial' => '601234567',
            'dni' => '33333333C',
            'created_at' => now()->subDays(2),
        ]);

        $duplicate = Customer::factory()->create([
            'first_names' => 'TOMASO',
            'last_names' => 'VEGA LOPEZ',
            'phone1_commercial' => '609876543',
            'dni' => '33333333c',
            'created_at' => now()->subDay(),
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'OTRO',
            'last_names' => 'NOMBRE',
            'phone1_commercial' => '601111111',
            'dni' => '33333333C',
            'primary_address' => 'Calle Test 4',
            'pf_existing_customer_id' => null,
        ], $user->id);

        $this->assertSame($older->id, $resolved->id);

        $duplicate->refresh();
        $this->assertSame($older->id, $duplicate->merged_into_id);

        $this->assertSame(
            1,
            Customer::query()->whereRaw('UPPER(TRIM(dni)) = ?', ['33333333C'])->count(),
        );
    }

    public function test_reuses_by_dni_when_phone_exists_with_different_name(): void
    {
        Customer::factory()->create([
            'first_names' => 'JUAN',
            'last_names' => 'PEREZ LOPEZ',
            'phone1_commercial' => '677888999',
            'dni' => null,
        ]);

        $byDni = Customer::factory()->create([
            'first_names' => 'MARIA',
            'last_names' => 'GONZALEZ',
            'phone1_commercial' => '611222333',
            'dni' => '44444444D',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'OTRA',
            'last_names' => 'PERSONA',
            'phone1_commercial' => '677 888 999',
            'dni' => '44444444D',
            'primary_address' => 'Calle Test 5',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertSame($byDni->id, $resolved->id);
    }

    public function test_creates_new_customer_when_no_match_exists(): void
    {
        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'NUEVO',
            'last_names' => 'CLIENTE',
            'phone1_commercial' => '644555666',
            'primary_address' => 'Calle Test 1',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $resolved->id,
            'first_names' => 'NUEVO',
            'last_names' => 'CLIENTE',
            'phone1_commercial' => '644555666',
        ]);
        $this->assertNull($resolved->merged_into_id);
    }

    public function test_puerta_fria_sale_stays_on_merged_customer(): void
    {
        $user = User::query()->firstOrFail();

        $existing = Customer::factory()->create([
            'first_names' => 'ANA',
            'last_names' => 'LOPEZ',
            'phone1_commercial' => '655666777',
        ]);

        $duplicate = Customer::factory()->create([
            'first_names' => 'ANA',
            'last_names' => 'LOPEZ',
            'phone' => '655666777',
        ]);

        $customer = $this->resolver->resolveOrCreate([
            'first_names' => 'ANA',
            'last_names' => 'LOPEZ',
            'phone1_commercial' => '655 666 777',
            'pf_existing_customer_id' => null,
        ], $user->id);

        $note = \App\Models\Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 100,
            'importe_comercial' => 100,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'puerta_fria',
        ]);

        $this->assertSame($existing->id, $venta->customer_id);
        $this->assertSame($existing->id, $customer->id);

        $duplicate->refresh();
        $this->assertSame($existing->id, $duplicate->merged_into_id);

        $this->assertSame(
            1,
            Customer::withoutGlobalScope(NotMergedScope::class)
                ->where(fn ($query) => $query
                    ->where('phone1_commercial', '655666777')
                    ->orWhere('phone', '655666777'))
                ->whereNull('merged_into_id')
                ->count(),
        );
    }

    public function test_merges_same_dni_duplicates_with_conflicting_fecha_nac_keeps_most_recent(): void
    {
        $user = User::query()->firstOrFail();

        $older = Customer::factory()->create([
            'first_names' => 'MARIA',
            'last_names' => 'OLIVA LAMAS',
            'dni' => '32416659E',
            'fecha_nac' => '1948-12-07',
            'phone1_commercial' => '611000001',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $newer = Customer::factory()->create([
            'first_names' => 'MARIA',
            'last_names' => 'OLIVA LAMAS TEIJEIRO',
            'dni' => '32416659e',
            'fecha_nac' => '1948-12-08',
            'phone1_commercial' => '611000002',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'MARIA',
            'last_names' => 'OLIVA',
            'phone1_commercial' => '611000003',
            'dni' => '32416659E',
            'pf_existing_customer_id' => null,
        ], $user->id);

        $this->assertSame($older->id, $resolved->id);
        $this->assertSame('1948-12-08', $resolved->getRawOriginal('fecha_nac'));

        $newer->refresh();
        $this->assertSame($older->id, $newer->merged_into_id);
    }

    public function test_applies_form_fecha_nac_when_reusing_customer_by_dni(): void
    {
        $existing = Customer::factory()->create([
            'first_names' => 'ANA',
            'last_names' => 'RUIZ',
            'dni' => '55555555F',
            'fecha_nac' => '1948-12-07',
            'phone1_commercial' => '622000001',
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'ANA',
            'last_names' => 'RUIZ',
            'phone1_commercial' => '622000002',
            'dni' => '55555555F',
            'fecha_nac' => '08/12/1948',
            'pf_existing_customer_id' => null,
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame('1948-12-08', $resolved->getRawOriginal('fecha_nac'));
    }

    public function test_merges_same_dni_when_existing_customer_selected_from_lookup(): void
    {
        $user = User::query()->firstOrFail();

        $selected = Customer::factory()->create([
            'first_names' => 'LUIS',
            'last_names' => 'MARTIN',
            'dni' => '66666666G',
            'phone1_commercial' => '633000001',
        ]);

        $duplicate = Customer::factory()->create([
            'first_names' => 'LUIS',
            'last_names' => 'MARTINEZ',
            'dni' => '66666666g',
            'phone1_commercial' => '633000002',
            'created_at' => now()->subDay(),
        ]);

        $resolved = $this->resolver->resolveOrCreate([
            'first_names' => 'LUIS',
            'last_names' => 'MARTIN',
            'phone1_commercial' => '633000003',
            'dni' => '66666666G',
            'pf_existing_customer_id' => $selected->id,
        ], $user->id);

        $this->assertSame($selected->id, $resolved->id);

        $duplicate->refresh();
        $this->assertSame($selected->id, $duplicate->merged_into_id);

        $this->assertSame(
            1,
            Customer::query()->whereRaw('UPPER(TRIM(dni)) = ?', ['66666666G'])->count(),
        );
    }
}
