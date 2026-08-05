<?php

namespace Tests\Unit;

use App\Exports\RecoveredContractsExport;
use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Models\User;
use App\Models\Venta;
use App\Services\ContractRecovery\ContractFromImageRecovery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecoveredContractsExportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_headings_include_all_creation_form_document_fields(): void
    {
        $export = new RecoveredContractsExport(includeOrphanHints: false);
        $headings = $export->headings();

        $this->assertContains('Foto sorteo', $headings);
        $this->assertContains('Titularidad', $headings);
        $this->assertContains('Nómina', $headings);
        $this->assertContains('Pensión', $headings);
        $this->assertContains('Otros docs', $headings);
        $this->assertContains('Contrato firmado', $headings);
        $this->assertContains('Precontractual', $headings);
        $this->assertContains('DNI anverso', $headings);
        $this->assertContains('DNI reverso', $headings);
        $this->assertContains('Pendiente re-enganchar', $headings);
    }

    public function test_export_includes_added_recovery_item_and_pending_docs(): void
    {
        $user = User::factory()->create([
            'name' => 'Rafa',
            'last_name' => 'Recovery',
            'email' => 'rec-'.Str::random(6).'@test.local',
            'empleado_id' => (string) random_int(100, 999),
        ]);
        $customer = Customer::factory()->create([
            'first_names' => 'Ana',
            'last_names' => 'Recuperada',
            'dni' => 'X'.Str::upper(Str::random(7)),
        ]);
        $venta = Venta::create([
            'note_id' => null,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 100,
            'importe_comercial' => 100,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'nro_contr_adm' => 'R'.random_int(1000, 9999),
            'origen_venta' => 'puerta_fria',
            'observaciones_repartidor' => ContractFromImageRecovery::OBSERVACION_RECUPERADO,
            'contrato_firmado' => null,
            'precontractual' => null,
        ]);

        // note_id may be required - check if create failed
        if (! $venta->exists) {
            $this->markTestSkipped('Venta requires note_id in this environment');
        }

        ContratoRecoveryItem::query()->create([
            'status' => ContratoRecoveryItem::STATUS_ADDED,
            'documents' => [['type' => 'app', 'path' => 'x.jpg']],
            'dni' => $customer->dni,
            'nro_contr_adm' => $venta->nro_contr_adm,
            'cliente_nombre' => 'Ana Recuperada',
            'customer_id' => $customer->id,
            'venta_id' => $venta->id,
            'comercial_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        $export = new RecoveredContractsExport(includeOrphanHints: false);
        $rows = $export->collection();
        $this->assertTrue($rows->contains(fn ($row) => (int) ($row['venta']?->id) === (int) $venta->id));

        $mapped = $export->map($rows->first(fn ($row) => (int) ($row['venta']?->id) === (int) $venta->id));
        $this->assertContains($venta->nro_contr_adm, $mapped);

        $headings = $export->headings();
        $this->assertContains('Foto sorteo', $headings);
        $this->assertContains('Titularidad', $headings);
        $this->assertContains('Nómina', $headings);
        $this->assertContains('Pensión', $headings);
        $this->assertContains('Otros docs', $headings);
        $this->assertContains('Contrato firmado', $headings);

        $pendingIdx = array_search('Pendiente re-enganchar', $headings, true);
        $this->assertNotFalse($pendingIdx);
        $this->assertStringContainsString('foto_sorteo', (string) $mapped[$pendingIdx]);
        $this->assertStringContainsString('documento_titularidad', (string) $mapped[$pendingIdx]);

        $this->assertSame('Candidatos huérfanos', $headings[$pendingIdx + 1]);
        $this->assertSame('Docs auto asignados', $headings[$pendingIdx + 2]);
        $this->assertSame('Docs pendientes manual', $headings[$pendingIdx + 3]);
    }
}
