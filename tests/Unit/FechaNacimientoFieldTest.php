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

    public function test_format_display_shows_exact_database_value_regardless_of_timezone(): void
    {
        date_default_timezone_set('UTC');

        $this->assertSame('11/04/1970', FechaNacimientoField::formatDisplay('1970-04-11'));
        $this->assertSame('01/10/1942', FechaNacimientoField::formatDisplay('1942-10-01'));
        $this->assertSame('11-04-1970', FechaNacimientoField::formatDisplay('1970-04-11', 'd-m-Y'));
        $this->assertSame('1970-04-11', FechaNacimientoField::formatDisplay('1970-04-11', 'Y-m-d'));
    }

    public function test_normalize_stored_string_accepts_mysql_datetime_prefix(): void
    {
        $this->assertSame('1970-04-11', FechaNacimientoField::normalizeStoredString('1970-04-11 00:00:00'));
        $this->assertSame('1942-10-01', FechaNacimientoField::normalizeStoredString('1942-10-01 00:00:00'));
    }

    public function test_to_storage_date_string_keeps_calendar_parts_from_carbon(): void
    {
        date_default_timezone_set('UTC');

        $date = \Carbon\Carbon::createFromDate(1970, 4, 11)->startOfDay();

        $this->assertSame('1970-04-11', FechaNacimientoField::toStorageDateString($date));
    }

    public function test_format_display_prevents_mask_mangling_y_m_d_values(): void
    {
        $this->assertSame('08/12/1948', FechaNacimientoField::formatDisplay('1948-12-08'));
        $this->assertSame('07/12/1948', FechaNacimientoField::formatDisplay('1948-12-07'));
    }
}
