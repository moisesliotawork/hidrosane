<?php

namespace Tests\Unit;

use App\Support\Filament\VentaCustomerRelationshipSection;
use PHPUnit\Framework\TestCase;

class VentaCustomerRelationshipSectionTest extends TestCase
{
    public function test_mutate_data_before_fill_converts_iso_datetime_to_spanish_display(): void
    {
        $data = VentaCustomerRelationshipSection::mutateDataBeforeFill([
            'first_names' => 'Maria Oliva',
            'fecha_nac' => '1948-12-08T00:00:00.000000Z',
        ]);

        $this->assertSame('08/12/1948', $data['fecha_nac']);
        $this->assertSame('Maria Oliva', $data['first_names']);
    }

    public function test_mutate_data_before_fill_converts_database_date_to_spanish_display(): void
    {
        $data = VentaCustomerRelationshipSection::mutateDataBeforeFill([
            'fecha_nac' => '1948-12-07',
        ]);

        $this->assertSame('07/12/1948', $data['fecha_nac']);
    }

    public function test_mutate_data_before_save_persists_spanish_input_as_database_date(): void
    {
        $data = VentaCustomerRelationshipSection::mutateDataBeforeSave([
            'fecha_nac' => '08/12/1948',
        ]);

        $this->assertSame('1948-12-08', $data['fecha_nac']);
    }

    public function test_mutate_data_before_save_normalizes_iso_datetime_for_storage(): void
    {
        $data = VentaCustomerRelationshipSection::mutateDataBeforeSave([
            'fecha_nac' => '1948-12-08T00:00:00.000000Z',
        ]);

        $this->assertSame('1948-12-08', $data['fecha_nac']);
    }
}
