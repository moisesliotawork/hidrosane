<?php

namespace Tests\Unit;

use App\Casts\SafeDateCast;
use App\Models\Customer;
use Tests\TestCase;

class SafeDateCastTest extends TestCase
{
    public function test_get_returns_null_for_impossible_spanish_date(): void
    {
        $cast = new SafeDateCast;
        $customer = new Customer;

        $result = $cast->get($customer, 'fecha_nac', '19/47/1019', [
            'fecha_nac' => '19/47/1019',
        ]);

        $this->assertNull($result);
    }

    public function test_get_parses_valid_database_date(): void
    {
        $cast = new SafeDateCast;
        $customer = new Customer;

        $result = $cast->get($customer, 'fecha_nac', '1945-07-29', [
            'fecha_nac' => '1945-07-29',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('1945-07-29', $result->format('Y-m-d'));
    }

    public function test_get_rejects_birth_dates_before_1936_on_new_spanish_input_only(): void
    {
        $cast = new SafeDateCast;
        $customer = new Customer;

        $result = $cast->get($customer, 'fecha_nac', '1935-12-31', [
            'fecha_nac' => '1935-12-31',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('1935-12-31', $result->format('Y-m-d'));
    }

    public function test_set_rejects_new_spanish_dates_before_1936(): void
    {
        $cast = new SafeDateCast;
        $customer = new Customer;

        $result = $cast->set($customer, 'fecha_nac', '01/01/1935', []);

        $this->assertNull($result['fecha_nac']);
    }

    public function test_set_rejects_invalid_input(): void
    {
        $cast = new SafeDateCast;
        $customer = new Customer;

        $result = $cast->set($customer, 'fecha_nac', '19/47/1019', []);

        $this->assertNull($result['fecha_nac']);
    }

    public function test_set_keeps_database_date_string_as_is(): void
    {
        $cast = new SafeDateCast;
        $customer = new Customer;

        $result = $cast->set($customer, 'fecha_nac', '1970-04-11', []);

        $this->assertSame('1970-04-11', $result['fecha_nac']);
    }
}
