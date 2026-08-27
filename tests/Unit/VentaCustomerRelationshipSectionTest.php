<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\Filament\VentaCustomerRelationshipSection;
use Tests\TestCase;

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

    public function test_mutate_data_before_fill_converts_iso_datetime_to_spanish_display_in_madrid_timezone(): void
    {
        config(['app.timezone' => 'Europe/Madrid']);
        date_default_timezone_set('Europe/Madrid');

        $customer = new Customer;
        $customer->setRawAttributes(['id' => 1, 'fecha_nac' => '1947-09-18']);
        $iso = $customer->attributesToArray()['fecha_nac'];

        $data = VentaCustomerRelationshipSection::mutateDataBeforeFill([
            'first_names' => 'Maria Teresa',
            'fecha_nac' => $iso,
        ]);

        $this->assertSame('18/09/1947', $data['fecha_nac']);
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
