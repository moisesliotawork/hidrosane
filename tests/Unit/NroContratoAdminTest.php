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
            $this->assertNotContains('1001', $values, $term);
            $this->assertNotContains('791', $values, $term);
            $this->assertNotContains('1188', $values, $term);
        }
    }

    public function test_b_suffix_does_not_include_the_main_contract(): void
    {
        $values = NroContratoAdmin::searchValues('1-B');

        $this->assertContains('1-B', $values);
        $this->assertContains('001-B', $values);
        $this->assertNotContains('1', $values);
    }
}
