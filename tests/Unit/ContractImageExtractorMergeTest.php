<?php

namespace Tests\Unit;

use App\Services\ContractRecovery\ContractImageExtractor;
use PHPUnit\Framework\TestCase;

class ContractImageExtractorMergeTest extends TestCase
{
    public function test_merge_prefers_app_contract_over_albaran(): void
    {
        $extractor = new ContractImageExtractor;

        [$merged, $conflicts] = $extractor->merge([
            [
                'type' => ContractImageExtractor::TYPE_ALBARAN,
                'data' => array_merge($extractor->emptyPayload(), [
                    'dni' => '11111111A',
                    'importe_total' => '100',
                    'nro_contr_adm' => null,
                ]),
            ],
            [
                'type' => ContractImageExtractor::TYPE_APP,
                'data' => array_merge($extractor->emptyPayload(), [
                    'dni' => '22222222B',
                    'importe_total' => '200',
                    'nro_contr_adm' => '1189',
                ]),
            ],
        ]);

        $this->assertSame('22222222B', $merged['dni']);
        $this->assertSame('200', $merged['importe_total']);
        $this->assertSame('1189', $merged['nro_contr_adm']);
        $this->assertNotEmpty($conflicts);
    }

    public function test_merge_fills_gaps_from_lower_priority(): void
    {
        $extractor = new ContractImageExtractor;

        [$merged] = $extractor->merge([
            [
                'type' => ContractImageExtractor::TYPE_APP,
                'data' => array_merge($extractor->emptyPayload(), [
                    'dni' => '35301073Y',
                    'nro_contr_adm' => '1189',
                ]),
            ],
            [
                'type' => ContractImageExtractor::TYPE_ALBARAN,
                'data' => array_merge($extractor->emptyPayload(), [
                    'iban' => 'ES3620805118313000000892',
                    'productos_texto' => 'laser espalda',
                ]),
            ],
        ]);

        $this->assertSame('35301073Y', $merged['dni']);
        $this->assertSame('1189', $merged['nro_contr_adm']);
        $this->assertSame('ES3620805118313000000892', $merged['iban']);
        $this->assertSame('laser espalda', $merged['productos_texto']);
    }
}
