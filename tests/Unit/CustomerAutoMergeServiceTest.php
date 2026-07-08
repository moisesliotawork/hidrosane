<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerAutoMergeRun;
use App\Models\Note;
use App\Models\User;
use App\Services\CustomerAutoMergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerAutoMergeServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_automatic_merge_records_run_and_merges_eligible_pairs(): void
    {
        $user = User::query()->firstOrFail();
        $sharedSecondary = '663576633';

        $keeper = Customer::factory()->create([
            'first_names' => 'AUTO FUSION',
            'last_names' => 'KEEPER MERGE',
            'phone' => '981229239',
            'secondary_phone' => $sharedSecondary,
            'created_at' => now()->subDays(2),
        ]);

        $toDelete = Customer::factory()->create([
            'first_names' => 'AUTO FUSION',
            'last_names' => 'KEEPER MERGE',
            'phone' => '981229238',
            'secondary_phone' => $sharedSecondary,
            'created_at' => now()->subDay(),
        ]);

        Note::factory()->create([
            'customer_id' => $toDelete->id,
            'user_id' => $user->id,
        ]);

        $run = app(CustomerAutoMergeService::class)->run($user->id, 'scheduled');

        $this->assertSame(1, $run->merged_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertSame('scheduled', $run->trigger);
        $this->assertNotNull($run->ran_at);

        $this->assertDatabaseHas('customer_auto_merge_runs', [
            'id' => $run->id,
            'merged_count' => 1,
            'failed_count' => 0,
            'trigger' => 'scheduled',
        ]);

        $this->assertSame($keeper->id, $toDelete->fresh()->merged_into_id);
        $this->assertSame(1, Note::where('customer_id', $keeper->id)->count());

        $latest = CustomerAutoMergeRun::latestRun();
        $this->assertNotNull($latest);
        $this->assertSame($run->id, $latest->id);
    }

    public function test_automatic_merge_records_zero_when_no_pairs_exist(): void
    {
        $run = app(CustomerAutoMergeService::class)->run(trigger: 'scheduled');

        $this->assertSame(0, $run->merged_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertDatabaseCount('customer_auto_merge_runs', 1);
    }
}
