<?php

namespace Tests\Unit;

use App\Models\Note;
use Carbon\Carbon;
use Tests\TestCase;

class RetenTabDatesTest extends TestCase
{
    public function test_reten_tabs_use_assignment_date_column(): void
    {
        $this->assertSame('assignment_date', Note::RETEN_TAB_DATE_COLUMN);
    }

    public function test_reten_tab_dates_use_business_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 23:30:00', 'UTC'));

        $dates = Note::retenTabDates();

        $this->assertSame('2026-07-14', $dates['today']);
        $this->assertSame('2026-07-13', $dates['yesterday']);

        Carbon::setTestNow();
    }
}
