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

        $matches = Customer::query()
            ->whereIn('id', [$first->id, $second->id])
            ->whereIn('id', CustomerDuplicateSearchService::duplicateIdsSubquery())
            ->count();

        $this->assertSame(2, $matches);
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

        $matches = Customer::query()
            ->whereIn('id', [$first->id, $second->id])
            ->whereIn('id', CustomerDuplicateSearchService::duplicateIdsSubquery())
            ->count();

        $this->assertSame(0, $matches);
    }
}
