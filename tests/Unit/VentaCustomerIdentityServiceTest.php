<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\VentaCustomerIdentityService;
use Tests\TestCase;

class VentaCustomerIdentityServiceTest extends TestCase
{
    public function test_customer_data_changed_detects_fecha_nac_correction(): void
    {
        $customer = new Customer;
        $customer->setRawAttributes([
            'first_names' => 'Maria Oliva',
            'last_names' => 'Lamas Tejeiro',
            'dni' => '32416659E',
            'fecha_nac' => '1948-12-07',
        ]);

        $this->assertTrue(VentaCustomerIdentityService::customerDataChanged($customer, [
            'first_names' => 'Maria Oliva',
            'last_names' => 'Lamas Tejeiro',
            'dni' => '32416659E',
            'fecha_nac' => '08/12/1948',
        ]));
    }

    public function test_customer_data_changed_ignores_same_fecha_nac_in_different_formats(): void
    {
        $customer = new Customer;
        $customer->setRawAttributes([
            'first_names' => 'Maria Oliva',
            'last_names' => 'Lamas Tejeiro',
            'dni' => '32416659E',
            'fecha_nac' => '1948-12-08',
        ]);

        $this->assertFalse(VentaCustomerIdentityService::customerDataChanged($customer, [
            'first_names' => 'Maria Oliva',
            'last_names' => 'Lamas Tejeiro',
            'dni' => '32416659E',
            'fecha_nac' => '08/12/1948',
        ]));
    }

    public function test_identity_change_does_not_include_fecha_nac_only_updates(): void
    {
        $customer = new Customer;
        $customer->setRawAttributes([
            'first_names' => 'Maria Oliva',
            'last_names' => 'Lamas Tejeiro',
            'dni' => '32416659E',
            'fecha_nac' => '1948-12-07',
        ]);

        $this->assertFalse(VentaCustomerIdentityService::identityChanged($customer, [
            'first_names' => 'Maria Oliva',
            'last_names' => 'Lamas Tejeiro',
            'dni' => '32416659E',
            'fecha_nac' => '08/12/1948',
        ]));
    }
}
