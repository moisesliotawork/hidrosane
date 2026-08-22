<?php

namespace Tests\Unit;

use App\Support\NroContratoAdmin;
use Tests\TestCase;

class NroContratoAdminTest extends TestCase
{
    public function test_one_and_padded_one_find_contract_one_not_contains_matches(): void
    {
        foreach (['1', '01', '001', '0001', '00001'] as $term) {
            $values = NroContratoAdmin::searchValues($term);

            $this->assertContains('1', $values, $term);
            $this->assertContains('001', $values, $term);
            $this->assertContains('00001', $values, $term);
            $this->assertContains('1-B', $values, $term);
            $this->assertContains('1B', $values, $term);
            $this->assertNotContains('1001', $values, $term);
            $this->assertNotContains('791', $values, $term);
            $this->assertNotContains('1188', $values, $term);
        }
    }

    public function test_hyphen_b_does_not_include_the_main_contract(): void
    {
        $values = NroContratoAdmin::searchValues('1-B');

        $this->assertContains('1-B', $values);
        $this->assertContains('001-B', $values);
        $this->assertContains('1B', $values);
        $this->assertNotContains('1', $values);
    }

    public function test_abby_b_without_hyphen_finds_titular_and_b(): void
    {
        $values = NroContratoAdmin::searchValues('382B');

        $this->assertContains('382', $values);
        $this->assertContains('382B', $values);
        $this->assertContains('382-B', $values);
        $this->assertNotContains('3821', $values);
    }

    public function test_plain_number_also_finds_abby_b_without_hyphen(): void
    {
        $values = NroContratoAdmin::searchValues('382');

        $this->assertContains('382', $values);
        $this->assertContains('382B', $values);
        $this->assertContains('382-B', $values);
    }
}
