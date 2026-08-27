<?php

namespace Tests\Unit;

use App\Services\ContractRecovery\ContractImageExtractor;
use App\Services\ContractRecovery\ContractVoiceExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContractVoiceExtractorTest extends TestCase
{
    public function test_extract_from_transcript_maps_dictado_fields(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'dni' => '52490318V',
                            'nro_contr_adm' => '1189',
                            'cliente_nombre' => 'Jose Angel Entenza',
                            'fecha_venta' => '02-10-2025',
                            'fecha_entrega' => '03-10-2025',
                            'comercial_codes' => '008 - 004',
                            'repartidor_code' => '005',
                            'nro_albaran' => null,
                            'horario_entrega' => null,
                            'importe_total' => null,
                            'entrada' => null,
                            'cuota_mensual' => null,
                            'num_cuotas' => null,
                            'iban' => null,
                            'productos_texto' => null,
                            'direccion' => null,
                            'telefonos' => null,
                            'observaciones' => null,
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        config(['services.openai.api_key' => 'test-key']);

        $merged = (new ContractVoiceExtractor(new ContractImageExtractor))
            ->extractFromTranscript('Cliente Jose Angel Entenza DNI 52490318V contrato 1189...');

        $this->assertSame('52490318V', $merged['dni']);
        $this->assertSame('1189', $merged['nro_contr_adm']);
        $this->assertSame('2025-10-02', $merged['fecha_venta']);
        $this->assertSame('2025-10-03', $merged['fecha_entrega']);
        $this->assertSame('008,004', $merged['comercial_codes']);
        $this->assertSame('005', $merged['repartidor_code']);
        $this->assertStringContainsString('Jose Angel', (string) $merged['_transcript']);
    }
}
