<?php

namespace Tests\Unit;

use App\Support\NextNroContrAdm;
use Tests\TestCase;

class NextNroContrAdmTest extends TestCase
{
    public function test_empty_starts_at_1023(): void
    {
        $this->assertSame('1023', NextNroContrAdm::fromExisting([]));
    }

    public function test_numeric_max_wins_over_string_max_999(): void
    {
        $this->assertSame(
            '2305',
            NextNroContrAdm::fromExisting(['999', '1000', '01000', '2304', '2304-B']),
        );
    }

    public function test_skips_numbers_already_taken(): void
    {
        $this->assertSame('1001', NextNroContrAdm::fromExisting(['999', '1000', '01000']));
    }

    public function test_titular_integer_from_padded_and_b_suffix(): void
    {
        $this->assertSame(1000, NextNroContrAdm::titularInteger('01000'));
        $this->assertSame(2304, NextNroContrAdm::titularInteger('2304-B'));
        $this->assertNull(NextNroContrAdm::titularInteger('DEMVAR1'));
    }
}
