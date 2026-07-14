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

    public function test_parse_rejects_birth_dates_before_1936_in_spanish_input(): void
    {
        $this->assertNull(FechaNacimientoField::normalizeForStorage('01/01/1935'));
    }

    public function test_parse_keeps_legacy_database_dates_before_1936(): void
    {
        $this->assertSame('1935-12-31', FechaNacimientoField::normalizeForStorage('1935-12-31'));

        $date = FechaNacimientoField::parse('1935-07-29');
        $this->assertNotNull($date);
        $this->assertSame('1935-07-29', $date->format('Y-m-d'));
    }

    public function test_parse_accepts_minimum_allowed_year(): void
    {
        $this->assertNotNull(FechaNacimientoField::parse('01/01/1936'));
        $this->assertSame('1936-01-01', FechaNacimientoField::normalizeForStorage('01/01/1936'));
    }

    public function test_normalize_for_storage_rejects_impossible_spanish_date(): void
    {
        $this->assertNull(FechaNacimientoField::normalizeForStorage('19/47/1019'));
    }

    public function test_normalize_for_storage_keeps_calendar_date_when_carbon_is_shifted_to_utc(): void
    {
        date_default_timezone_set('UTC');

        $madridMidnight = \Carbon\Carbon::parse('1980-07-15', 'Europe/Madrid')->startOfDay();
        $utcShifted = $madridMidnight->copy()->timezone('UTC');

        $this->assertSame('1980-07-14', $utcShifted->format('Y-m-d'));
        $this->assertSame('1980-07-15', FechaNacimientoField::normalizeForStorage($utcShifted));
    }

    public function test_format_display_shows_exact_database_value_regardless_of_timezone(): void
    {
        date_default_timezone_set('UTC');

        $this->assertSame('15/07/1980', FechaNacimientoField::formatDisplay('1980-07-15'));
        $this->assertSame('15-07-1980', FechaNacimientoField::formatDisplay('1980-07-15', 'd-m-Y'));
        $this->assertSame('1980-07-15', FechaNacimientoField::formatDisplay('1980-07-15', 'Y-m-d'));
    }

    public function test_parse_normalizes_shifted_carbon_to_business_calendar_date(): void
    {
        date_default_timezone_set('UTC');

        $utcShifted = \Carbon\Carbon::parse('1980-07-15', 'Europe/Madrid')
            ->startOfDay()
            ->timezone('UTC');

        $date = FechaNacimientoField::parse($utcShifted);

        $this->assertNotNull($date);
        $this->assertSame('1980-07-15', $date->format('Y-m-d'));
        $this->assertSame('15/07/1980', $date->format('d/m/Y'));
    }
}
