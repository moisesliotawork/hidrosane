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

    public function test_normalize_maps_contrato_header_fields(): void
    {
        $extractor = new ContractImageExtractor;

        $normalized = $extractor->normalizeExtracted(array_merge($extractor->emptyPayload(), [
            'nro_contr_adm' => 'Cod.Contrato 1189',
            'fecha_venta' => '02-10-2025',
            'fecha_entrega' => '03/10/2025',
            'comercial_codes' => '008 - 004',
            'repartidor_code' => 'Rep. 005',
            'horario_entrega' => 'td',
        ]));

        $this->assertSame('1189', $normalized['nro_contr_adm']);
        $this->assertSame('2025-10-02', $normalized['fecha_venta']);
        $this->assertSame('2025-10-03', $normalized['fecha_entrega']);
        $this->assertSame('008,004', $normalized['comercial_codes']);
        $this->assertSame('005', $normalized['repartidor_code']);
        $this->assertSame('TD', $normalized['horario_entrega']);
    }
}
