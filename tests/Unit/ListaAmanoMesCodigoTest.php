<?php

namespace Tests\Unit;

use App\Models\ListaAmano;
use PHPUnit\Framework\TestCase;

class ListaAmanoMesCodigoTest extends TestCase
{
    public function test_parse_mes_codigo_known_values(): void
    {
        $this->assertSame(
            ['mes' => 5, 'anio' => 2025, 'codigo' => 'Mayo25'],
            ListaAmano::parseMesCodigo('Mayo25'),
        );
        $this->assertSame(
            ['mes' => 9, 'anio' => 2025, 'codigo' => 'Sept25'],
            ListaAmano::parseMesCodigo('Sept25'),
        );
        $this->assertSame(
            ['mes' => 1, 'anio' => 2026, 'codigo' => 'Enero26'],
            ListaAmano::parseMesCodigo('Enero26'),
        );
    }

    public function test_parse_mes_codigo_rejects_invalid(): void
    {
        $this->assertNull(ListaAmano::parseMesCodigo(''));
        $this->assertNull(ListaAmano::parseMesCodigo('Foo99'));
    }
}
