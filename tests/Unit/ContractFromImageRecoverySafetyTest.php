<?php

namespace Tests\Unit;

use App\Services\ContractRecovery\ContractFromImageRecovery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ContractFromImageRecoverySafetyTest extends TestCase
{
    public function test_service_source_never_calls_destructive_venta_methods(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/app/Services/ContractRecovery/ContractFromImageRecovery.php');
        $this->assertIsString($src);

        foreach (['->delete(', '->forceDelete(', '::destroy(', "DB::table('ventas')->delete"] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $src,
                "No debe existir operación destructiva: {$needle}"
            );
        }

        $this->assertStringContainsString('PROTEGIDO', $src);
        $this->assertStringContainsString('attachDocumentsWithoutOverwrite', $src);
        $this->assertStringContainsString('attachOfertaProductos', $src);
        $this->assertStringContainsString('validateOfertaProductos', $src);
    }

    public function test_nro_candidates_include_padding_variants(): void
    {
        $svc = new ContractFromImageRecovery;
        $m = new ReflectionMethod(ContractFromImageRecovery::class, 'nroCandidates');
        $m->setAccessible(true);
        /** @var list<string> $candidates */
        $candidates = $m->invoke($svc, '1189');

        $this->assertContains('1189', $candidates);
        $this->assertContains('01189', $candidates);
    }

    public function test_validate_oferta_productos_rejects_empty_without_db(): void
    {
        $svc = new ContractFromImageRecovery;

        $this->assertSame(
            'Indica al menos una oferta con productos (Editar → Oferta y productos).',
            $svc->validateOfertaProductos([])
        );
    }

    public function test_normalize_productos_externos_prefers_structured_list(): void
    {
        $svc = new ContractFromImageRecovery;
        $m = new ReflectionMethod(ContractFromImageRecovery::class, 'normalizeProductosExternos');
        $m->setAccessible(true);

        $this->assertSame(
            ['Depurador Vita'],
            $m->invoke($svc, [
                'productos_externos' => ['Depurador Vita'],
                'productos_texto' => 'OCR basura',
            ])
        );

        $this->assertSame(
            ['Nombre libre'],
            $m->invoke($svc, [
                'productos_externos' => [['value' => 'Nombre libre']],
            ])
        );
    }

    public function test_recovery_observaciones_tag(): void
    {
        $svc = new ContractFromImageRecovery;
        $m = new ReflectionMethod(ContractFromImageRecovery::class, 'recoveryObservaciones');
        $m->setAccessible(true);

        $this->assertSame(
            ContractFromImageRecovery::OBSERVACION_RECUPERADO,
            $m->invoke($svc, null)
        );
        $this->assertSame(
            ContractFromImageRecovery::OBSERVACION_RECUPERADO."\nNota OCR",
            $m->invoke($svc, 'Nota OCR')
        );
    }
}
