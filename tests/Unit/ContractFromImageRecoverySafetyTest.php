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
}
