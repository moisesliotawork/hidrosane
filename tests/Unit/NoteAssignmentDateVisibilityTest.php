<?php

namespace Tests\Unit;

use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NoteAssignmentDateVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        Schema::dropIfExists('notes_assign_date_test');
        Schema::create('notes_assign_date_test', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comercial_id')->nullable();
            $table->dateTime('assignment_date')->nullable();
            $table->dateTime('visit_date')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notes_assign_date_test');
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_normalize_stores_calendar_day_in_app_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-24 15:30:00', 'Europe/Madrid'));

        $normalized = Note::normalizeCommercialAssignmentDate(null);

        $this->assertSame('UTC', $normalized->timezoneName);
        $this->assertSame('2026-07-24 00:00:00', $normalized->toDateTimeString());
    }

    public function test_hoy_tab_matches_madrid_midnight_stored_as_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-24 15:30:00', 'Europe/Madrid'));

        // Caso legacy: startOfDay Madrid serializado a UTC (día anterior 22:00)
        DB::table('notes_assign_date_test')->insert([
            'id' => 1,
            'comercial_id' => 10,
            'assignment_date' => '2026-07-23 22:00:00',
            'visit_date' => null,
        ]);

        // Caso nuevo: día de calendario naive en TZ app
        DB::table('notes_assign_date_test')->insert([
            'id' => 2,
            'comercial_id' => 10,
            'assignment_date' => '2026-07-24 00:00:00',
            'visit_date' => null,
        ]);

        $appTz = config('app.timezone', 'UTC');
        $start = Carbon::parse('2026-07-24', Note::businessTimezone())->startOfDay()->timezone($appTz);
        $end = Carbon::parse('2026-07-24', Note::businessTimezone())->endOfDay()->timezone($appTz);

        $ids = DB::table('notes_assign_date_test')
            ->whereNotNull('comercial_id')
            ->whereBetween('assignment_date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([1, 2], $ids);
    }
}
