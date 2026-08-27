<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Services\ContractRecovery\ContractImageExtractor;
use App\Services\ContractRecovery\OrphanDocumentMatcher;
use App\Support\Filament\VentaDocumentUpload;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrphanDocumentMatcherTest extends TestCase
{
    use DatabaseTransactions;

    public function test_parse_filename_extracts_field_and_upload_time(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $meta = $matcher->parseFilename('20260105_090221_911_adm_carolina_precontractual.pdf');

        $this->assertSame('precontractual', $meta['field']);
        $this->assertSame('911', $meta['empleado_id']);
        $this->assertInstanceOf(Carbon::class, $meta['uploaded_at']);
        $this->assertSame('2026-01-05', $meta['uploaded_at']->toDateString());
    }

    public function test_document_fields_cover_creation_form_and_contrato_firmado(): void
    {
        $fields = OrphanDocumentMatcher::documentFields();
        foreach (VentaDocumentUpload::creationFormDocumentFields() as $field) {
            $this->assertContains($field, $fields);
        }
        $this->assertContains('contrato_firmado', $fields);
    }

    public function test_parse_filename_covers_all_creation_form_fields_and_albaran_alias(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);

        foreach (VentaDocumentUpload::creationFormDocumentFields() as $field) {
            $meta = $matcher->parseFilename("20260801_120000_010_zara_{$field}.jpg");
            $this->assertSame($field, $meta['field'], "Expected field {$field}");
        }

        $albaran = $matcher->parseFilename('20260801_120000_010_zara_albaran.pdf');
        $this->assertSame('precontractual', $albaran['field']);

        $titularidad = $matcher->parseFilename('20260801_120000_010_zara_documento_titularidad.pdf');
        $this->assertSame('documento_titularidad', $titularidad['field']);
    }

    public function test_empty_slots_include_form_docs_not_only_basicos(): void
    {
        $venta = new Venta([
            'contrato_firmado' => 'ventas/c.pdf',
            'precontractual' => 'ventas/p.pdf',
            'dni_anverso' => 'ventas/a.jpg',
            'dni_reverso' => 'ventas/r.jpg',
            'foto_sorteo' => null,
            'documento_titularidad' => null,
            'nomina' => null,
            'pension' => null,
            'otros_documentos' => null,
        ]);

        $empty = app(OrphanDocumentMatcher::class)->emptySlots($venta);

        $this->assertContains('foto_sorteo', $empty);
        $this->assertContains('documento_titularidad', $empty);
        $this->assertContains('nomina', $empty);
        $this->assertContains('pension', $empty);
        $this->assertContains('otros_documentos', $empty);
        $this->assertNotContains('precontractual', $empty);
        $this->assertNotContains('contrato_firmado', $empty);
    }

    public function test_score_requires_matching_dni(): void
    {
        [$venta, $orphan] = $this->seedVentaAndOrphanMeta('33246893W', '2026-08-01');

        $matcher = app(OrphanDocumentMatcher::class);

        $this->assertSame(0, $matcher->scoreMatch($venta, $orphan, [
            'dni' => '99999999Z',
            'fecha_venta' => '2026-08-01',
        ]));

        $score = $matcher->scoreMatch($venta, $orphan, [
            'dni' => '33246893W',
            'fecha_venta' => '2026-08-01',
        ]);
        $this->assertGreaterThanOrEqual(90, $score);
    }

    public function test_score_rejects_far_fecha_promo_even_with_dni(): void
    {
        [$venta, $orphan] = $this->seedVentaAndOrphanMeta('33246893W', '2026-08-01');
        $matcher = app(OrphanDocumentMatcher::class);

        $this->assertSame(0, $matcher->scoreMatch($venta, $orphan, [
            'dni' => '33246893W',
            'fecha_venta' => '2025-01-15',
        ]));
    }

    public function test_is_clear_auto_match(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $this->assertTrue($matcher->isClearAutoMatch(95, false));
        $this->assertTrue($matcher->isClearAutoMatch(75, true));
        $this->assertFalse($matcher->isClearAutoMatch(75, false));
        $this->assertFalse($matcher->isClearAutoMatch(40, true));
    }

    public function test_propose_with_ocr_marks_auto_for_unique_strong_match(): void
    {
        [$venta, $orphan] = $this->seedVentaAndOrphanMeta('11111111A', '2026-07-15');

        $matcher = new OrphanDocumentMatcher(
            app(ContractImageExtractor::class),
            fn (string $type, string $path): array => [
                'dni' => '11111111A',
                'fecha_venta' => '2026-07-15',
            ],
        );
        $proposals = $matcher->propose([$venta], [$orphan], withOcr: true);

        $this->assertCount(1, $proposals);
        $this->assertSame('auto', $proposals[0]['action']);
        $this->assertSame('precontractual', $proposals[0]['field']);
        $this->assertGreaterThanOrEqual(90, $proposals[0]['score']);
    }

    public function test_apply_links_path_without_overwrite(): void
    {
        [$venta, $orphan] = $this->seedVentaAndOrphanMeta('22222222B', '2026-07-20');
        $venta->forceFill(['precontractual' => null])->saveQuietly();

        // Ensure public disk has a dummy file for exists() check — use Storage fake
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put($orphan['path'], 'fake');

        $matcher = app(OrphanDocumentMatcher::class);
        $result = $matcher->apply([[
            'venta_id' => $venta->id,
            'path' => $orphan['path'],
            'field' => 'precontractual',
            'action' => 'auto',
        ]]);

        $this->assertSame(1, $result['applied']);
        $this->assertSame($orphan['path'], $venta->fresh()->precontractual);

        // Second apply must not overwrite
        $result2 = $matcher->apply([[
            'venta_id' => $venta->id,
            'path' => 'ventas/other_precontractual.pdf',
            'field' => 'precontractual',
            'action' => 'auto',
        ]]);
        $this->assertSame(0, $result2['applied']);
        $this->assertSame($orphan['path'], $venta->fresh()->precontractual);
    }

    /**
     * @return array{0: Venta, 1: array{path: string, field: string, uploaded_at: Carbon, empleado_id: string, uploader_slug: string}}
     */
    private function seedVentaAndOrphanMeta(string $dni, string $fechaVenta): array
    {
        $user = User::factory()->create([
            'name' => 'Com',
            'last_name' => 'Test',
            'email' => 'orphan-'.Str::random(6).'@test.local',
            'empleado_id' => '010',
        ]);
        $customer = Customer::factory()->create([
            'dni' => $dni,
            'first_names' => 'Test',
            'last_names' => 'Cliente',
        ]);
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);
        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => $fechaVenta,
            'importe_total' => 100,
            'importe_comercial' => 100,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'nro_contr_adm' => 'T'.random_int(1000, 9999),
            'origen_venta' => 'puerta_fria',
            'precontractual' => null,
        ]);
        $venta->load(['customer', 'comercial']);

        $ymd = Carbon::parse($fechaVenta)->format('Ymd');
        $orphan = [
            'path' => "ventas/{$ymd}_120000_010_test_slug_precontractual.pdf",
            'field' => 'precontractual',
            'uploaded_at' => Carbon::parse($fechaVenta)->setTime(12, 0),
            'empleado_id' => '010',
            'uploader_slug' => 'test_slug',
        ];

        return [$venta, $orphan];
    }
}
