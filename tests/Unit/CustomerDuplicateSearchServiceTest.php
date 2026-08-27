<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\CustomerDuplicateSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerDuplicateSearchServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detects_duplicates_with_shared_phone_and_matching_name_without_dni(): void
    {
        $sharedSecondary = '663576632';

        $first = Customer::factory()->create([
            'first_names' => 'ANA ISABEL',
            'last_names' => 'CALVETE GONZÁLEZ',
            'phone' => '981229229',
            'secondary_phone' => $sharedSecondary,
            'dni' => null,
        ]);

        $second = Customer::factory()->create([
            'first_names' => 'ANA ISABEL',
            'last_names' => 'CALVETE GONZÁLEZ',
            'phone' => '981229226',
            'secondary_phone' => $sharedSecondary,
            'dni' => null,
        ]);

        $ids = CustomerDuplicateSearchService::findDuplicateIds();

        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    public function test_does_not_detect_different_names_with_shared_phone(): void
    {
        $sharedPhone = '611111111';

        $first = Customer::factory()->create([
            'first_names' => 'JUAN',
            'last_names' => 'PEREZ',
            'phone' => $sharedPhone,
            'dni' => null,
        ]);

        $second = Customer::factory()->create([
            'first_names' => 'MARIA',
            'last_names' => 'LOPEZ',
            'secondary_phone' => $sharedPhone,
            'dni' => null,
        ]);

        $ids = CustomerDuplicateSearchService::findDuplicateIds();

        $this->assertNotContains($first->id, $ids);
        $this->assertNotContains($second->id, $ids);
    }

    public function test_does_not_detect_same_full_name_without_shared_phone_or_dni(): void
    {
        $first = Customer::factory()->create([
            'first_names' => 'PEDRO',
            'last_names' => 'SANCHEZ LOPEZ',
            'phone' => '611111111',
            'dni' => null,
        ]);

        $second = Customer::factory()->create([
            'first_names' => 'PEDRO',
            'last_names' => 'SANCHEZ LOPEZ',
            'phone' => '622222222',
            'dni' => null,
        ]);

        $ids = CustomerDuplicateSearchService::findDuplicateIds();

        $this->assertNotContains($first->id, $ids);
        $this->assertNotContains($second->id, $ids);
    }

    public function test_find_auto_merge_pairs_of_two_returns_valid_pair(): void
    {
        $sharedSecondary = '663576632';

        $older = Customer::factory()->create([
            'first_names' => 'ANA ISABEL',
            'last_names' => 'CALVETE GONZÁLEZ',
            'phone' => '981229229',
            'secondary_phone' => $sharedSecondary,
            'dni' => null,
            'created_at' => now()->subDay(),
        ]);

        $newer = Customer::factory()->create([
            'first_names' => 'ANA ISABEL',
            'last_names' => 'CALVETE GONZÁLEZ',
            'phone' => '981229226',
            'secondary_phone' => $sharedSecondary,
            'dni' => null,
            'created_at' => now(),
        ]);

        $pairs = collect(CustomerDuplicateSearchService::findAutoMergePairsOfTwo())
            ->filter(fn (array $pair) => in_array($older->id, [$pair['keeper_id'], $pair['to_delete_id']], true))
            ->values()
            ->all();

        $this->assertCount(1, $pairs);
        $this->assertSame($older->id, $pairs[0]['keeper_id']);
        $this->assertSame($newer->id, $pairs[0]['to_delete_id']);
        $this->assertContains($sharedSecondary, $pairs[0]['shared_phones']);
    }

    public function test_find_auto_merge_pairs_excludes_groups_of_three(): void
    {
        $sharedPhone = '611111111';

        $first = Customer::factory()->create([
            'first_names' => 'CARLOS',
            'last_names' => 'RUIZ',
            'phone' => $sharedPhone,
            'dni' => null,
        ]);

        $second = Customer::factory()->create([
            'first_names' => 'CARLOS',
            'last_names' => 'RUIZ',
            'secondary_phone' => $sharedPhone,
            'dni' => null,
        ]);

        $third = Customer::factory()->create([
            'first_names' => 'CARLOS',
            'last_names' => 'RUIZ',
            'third_phone' => $sharedPhone,
            'dni' => null,
        ]);

        $groupIds = [$first->id, $second->id, $third->id];

        $pairs = collect(CustomerDuplicateSearchService::findAutoMergePairsOfTwo())
            ->filter(function (array $pair) use ($groupIds) {
                return in_array($pair['keeper_id'], $groupIds, true)
                    || in_array($pair['to_delete_id'], $groupIds, true);
            })
            ->values()
            ->all();

        $this->assertSame([], $pairs);
    }

    public function test_find_auto_merge_pairs_sorted_alphabetically(): void
    {
        $shared = '699999999';

        $zaraOlder = Customer::factory()->create([
            'first_names' => 'ZARA',
            'last_names' => 'ZULU',
            'phone' => '611111111',
            'secondary_phone' => $shared,
            'dni' => null,
        ]);

        Customer::factory()->create([
            'first_names' => 'ZARA',
            'last_names' => 'ZULU',
            'phone' => '622222222',
            'secondary_phone' => $shared,
            'dni' => null,
        ]);

        $anaOlder = Customer::factory()->create([
            'first_names' => 'ANA',
            'last_names' => 'ALFA',
            'phone' => '633333333',
            'secondary_phone' => $shared,
            'dni' => null,
        ]);

        Customer::factory()->create([
            'first_names' => 'ANA',
            'last_names' => 'ALFA',
            'phone' => '644444444',
            'secondary_phone' => $shared,
            'dni' => null,
        ]);

        $trackedIds = [$zaraOlder->id, $anaOlder->id];

        $pairs = collect(CustomerDuplicateSearchService::findAutoMergePairsOfTwo())
            ->filter(fn (array $pair) => in_array($pair['keeper_id'], $trackedIds, true))
            ->values()
            ->all();

        $this->assertCount(2, $pairs);
        $this->assertSame('ANA ALFA', $pairs[0]['name']);
        $this->assertSame('ZARA ZULU', $pairs[1]['name']);
    }
}
