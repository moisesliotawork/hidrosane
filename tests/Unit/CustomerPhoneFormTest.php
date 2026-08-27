<?php

namespace Tests\Unit;

use App\Filament\Support\CustomerPhoneForm;
use Tests\TestCase;

class CustomerPhoneFormTest extends TestCase
{
    public function test_normalizes_digits(): void
    {
        $this->assertSame('612345678', CustomerPhoneForm::normalizeDigits('612 345 678'));
        $this->assertNull(CustomerPhoneForm::normalizeDigits(''));
        $this->assertSame('12345', CustomerPhoneForm::normalizeDigits('12345'));
    }

    public function test_formats_mask(): void
    {
        $this->assertSame('612 345 678', CustomerPhoneForm::formatMask('612345678'));
    }
}
