<?php

namespace Tests\Unit;

use App\Models\Customer;
use Tests\TestCase;

class CustomerFechaNacSafetyTest extends TestCase
{
    public function test_fecha_nac_display_shows_exact_database_value(): void
    {
        $customer = new Customer;
        $customer->setRawAttributes([
            'id' => 1,
            'first_names' => 'Test',
            'last_names' => 'Cliente',
            'fecha_nac' => '1980-07-15',
        ]);

        $this->assertSame('1980-07-15', $customer->storedFechaNac());
        $this->assertSame('15/07/1980', $customer->fechaNacDisplay());
        $this->assertSame('15-07-1980', $customer->fechaNacDisplay('d-m-Y'));
    }

    public function test_safe_fecha_nac_returns_null_for_corrupt_value(): void
    {
        $customer = new Customer;
        $customer->setRawAttributes([
            'id' => 1,
            'first_names' => 'Test',
            'last_names' => 'Cliente',
            'fecha_nac' => '19/47/1019',
        ]);

        $this->assertNull($customer->safeFechaNac());
    }

    public function test_form_fillable_attributes_does_not_expose_corrupt_fecha_nac(): void
    {
        $customer = new Customer;
        $customer->setRawAttributes([
            'id' => 1,
            'first_names' => 'Test',
            'last_names' => 'Cliente',
            'fecha_nac' => '19/47/1019',
            'dni' => '12345678A',
        ]);

        $attributes = $customer->formFillableAttributes();

        $this->assertNull($attributes['fecha_nac']);
        $this->assertSame('12345678A', $attributes['dni']);
    }
}
