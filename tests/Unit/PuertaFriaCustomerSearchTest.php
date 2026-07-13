<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\PuertaFriaCustomerSearch;
use Tests\TestCase;

class PuertaFriaCustomerSearchTest extends TestCase
{
    public function test_split_lookup_name(): void
    {
        $this->assertSame(
            ['first_names' => 'Ana', 'last_names' => 'Ruiz Diaz'],
            PuertaFriaCustomerSearch::splitLookupName('Ana Ruiz Diaz'),
        );

        $this->assertSame(
            ['first_names' => 'Pedro', 'last_names' => ''],
            PuertaFriaCustomerSearch::splitLookupName('Pedro'),
        );
    }

    public function test_name_similarity_score(): void
    {
        $customer = new Customer([
            'first_names' => 'ADMI',
            'last_names' => 'CONTR',
        ]);

        $this->assertGreaterThanOrEqual(45, PuertaFriaCustomerSearch::nameSimilarityScore($customer, 'ADMI CONTR'));
        $this->assertGreaterThanOrEqual(45, PuertaFriaCustomerSearch::nameSimilarityScore($customer, 'admi'));
        $this->assertSame('ADMI CONTR', PuertaFriaCustomerSearch::displayName($customer));
    }

    public function test_invalid_phone_returns_message(): void
    {
        $result = PuertaFriaCustomerSearch::search('123', 'Cliente');

        $this->assertTrue($result['customers']->isEmpty());
        $this->assertSame('Introduce un teléfono válido de 9 dígitos.', $result['message']);
    }
}
