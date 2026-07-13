<?php

namespace Tests\Unit;

use App\Support\Filament\FechaNacimientoField;
use PHPUnit\Framework\TestCase;

class FechaNacimientoFieldTest extends TestCase
{
    public function test_parse_accepts_spanish_date_format(): void
    {
        $date = FechaNacimientoField::parse('29/07/1945');

        $this->assertNotNull($date);
        $this->assertSame('1945-07-29', $date->format('Y-m-d'));
    }

    public function test_parse_accepts_database_date_format(): void
    {
        $date = FechaNacimientoField::parse('1945-07-29');

        $this->assertNotNull($date);
        $this->assertSame('1945-07-29', $date->format('Y-m-d'));
    }

    public function test_parse_returns_null_for_invalid_value(): void
    {
        $this->assertNull(FechaNacimientoField::parse('no-es-fecha'));
    }

    public function test_parse_returns_null_for_impossible_spanish_date(): void
    {
        $this->assertNull(FechaNacimientoField::parse('19/47/1019'));
    }
}
