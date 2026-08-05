<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\User;
use App\Models\Venta;
use App\Services\ContractRecovery\ContractImageExtractor;
use App\Services\ContractRecovery\OrphanDocumentMatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Flujo de packs huérfanos: ventana −5/+4, clustering por minuto, ancla OCR, slots.
 *
 * Los tests de lógica pura y proposePacks (modelos en memoria) corren en sqlite.
 * apply() requiere MySQL:
 *   DB_CONNECTION=mysql DB_DATABASE=ohanaplus php artisan test --filter=OrphanDocumentPackRecoveryTest
 */
class OrphanDocumentPackRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recovery_window_accepts_minus_5_to_plus_4(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $fechaVenta = Carbon::parse('2026-07-15')->startOfDay();

        $this->assertTrue($matcher->isWithinRecoveryUploadWindow(
            $fechaVenta,
            Carbon::parse('2026-07-10')->startOfDay(),
        ));
        $this->assertTrue($matcher->isWithinRecoveryUploadWindow(
            $fechaVenta,
            Carbon::parse('2026-07-19')->startOfDay(),
        ));
        $this->assertTrue($matcher->isWithinRecoveryUploadWindow(
            $fechaVenta,
            $fechaVenta->copy(),
        ));

        $this->assertFalse($matcher->isWithinRecoveryUploadWindow(
            $fechaVenta,
            Carbon::parse('2026-07-09')->startOfDay(),
        ));
        $this->assertFalse($matcher->isWithinRecoveryUploadWindow(
            $fechaVenta,
            Carbon::parse('2026-07-20')->startOfDay(),
        ));
    }

    public function test_cluster_by_minute_groups_same_minute_and_empleado(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $t = Carbon::parse('2026-07-14 18:22:00');

        $orphans = [
            $this->orphanMeta('ventas/a_precontractual.pdf', 'precontractual', $t, '911'),
            $this->orphanMeta('ventas/a_dni_anverso.jpg', 'dni_anverso', $t->copy()->addSeconds(20), '911'),
            $this->orphanMeta('ventas/a_dni_reverso.jpg', 'dni_reverso', $t->copy()->addSeconds(40), '911'),
            $this->orphanMeta('ventas/other_precontractual.pdf', 'precontractual', $t->copy()->addMinute(), '911'),
            $this->orphanMeta('ventas/other_emp.pdf', 'precontractual', $t, '010'),
        ];

        $clusters = $matcher->clusterByMinute($orphans);

        $this->assertCount(3, $clusters);
        $packKey = $matcher->minuteClusterKey($orphans[0]);
        $this->assertCount(3, $clusters[$packKey]);
    }

    public function test_map_cluster_fills_typed_slots_and_otros_for_unknown(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $t = Carbon::parse('2026-07-14 18:22:00');

        $cluster = [
            $this->orphanMeta('ventas/p.pdf', 'precontractual', $t),
            $this->orphanMeta('ventas/a.jpg', 'dni_anverso', $t),
            $this->orphanMeta('ventas/r.jpg', 'dni_reverso', $t),
            $this->orphanMeta('ventas/extra.pdf', null, $t),
        ];

        $empty = ['precontractual', 'dni_anverso', 'dni_reverso', 'otros_documentos', 'nomina'];
        $map = $matcher->mapClusterToEmptySlots($cluster, $empty);

        $this->assertSame('ventas/p.pdf', $map['precontractual']);
        $this->assertSame('ventas/a.jpg', $map['dni_anverso']);
        $this->assertSame('ventas/r.jpg', $map['dni_reverso']);
        $this->assertSame('ventas/extra.pdf', $map['otros_documentos']);
        $this->assertArrayNotHasKey('nomina', $map);
    }

    public function test_map_cluster_fills_priority_slots_for_uuid_pack_without_field(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $t = Carbon::parse('2025-09-05 17:52:00');

        $cluster = [
            $this->orphanMeta('ventas/aaa.jpeg', null, $t),
            $this->orphanMeta('ventas/bbb.jpeg', null, $t),
            $this->orphanMeta('ventas/ccc.jpeg', null, $t),
            $this->orphanMeta('ventas/ddd.jpeg', null, $t),
        ];

        $empty = ['precontractual', 'dni_anverso', 'dni_reverso', 'documento_titularidad', 'contrato_firmado'];
        $map = $matcher->mapClusterToEmptySlots($cluster, $empty);

        $this->assertSame('ventas/aaa.jpeg', $map['precontractual']);
        $this->assertSame('ventas/bbb.jpeg', $map['dni_anverso']);
        $this->assertSame('ventas/ccc.jpeg', $map['dni_reverso']);
        $this->assertSame('ventas/ddd.jpeg', $map['documento_titularidad']);
        $this->assertArrayNotHasKey('contrato_firmado', $map);
    }

    public function test_map_cluster_skips_already_filled_slots(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $t = Carbon::parse('2026-07-14 18:22:00');

        $cluster = [
            $this->orphanMeta('ventas/p.pdf', 'precontractual', $t),
            $this->orphanMeta('ventas/a.jpg', 'dni_anverso', $t),
        ];

        $map = $matcher->mapClusterToEmptySlots($cluster, ['dni_anverso']);

        $this->assertSame(['dni_anverso' => 'ventas/a.jpg'], $map);
    }

    public function test_filter_orphans_in_recovery_window(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $fechaVenta = Carbon::parse('2026-07-15');

        $orphans = [
            $this->orphanMeta('ventas/in.pdf', 'precontractual', Carbon::parse('2026-07-12')),
            $this->orphanMeta('ventas/out.pdf', 'precontractual', Carbon::parse('2026-07-01')),
            $this->orphanMeta('ventas/edge.pdf', 'precontractual', Carbon::parse('2026-07-19')),
        ];

        $filtered = $matcher->filterOrphansInRecoveryWindow($orphans, $fechaVenta);

        $this->assertCount(2, $filtered);
        $this->assertSame(['ventas/in.pdf', 'ventas/edge.pdf'], array_column($filtered, 'path'));
    }

    public function test_pick_pack_anchor_prefers_precontractual(): void
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $t = Carbon::parse('2026-07-14 18:22:00');

        $cluster = [
            $this->orphanMeta('ventas/a.jpg', 'dni_anverso', $t),
            $this->orphanMeta('ventas/p.pdf', 'precontractual', $t),
            $this->orphanMeta('ventas/r.jpg', 'dni_reverso', $t),
        ];

        $anchor = $matcher->pickPackAnchor($cluster);
        $this->assertSame('ventas/p.pdf', $anchor['path']);
    }

    public function test_propose_packs_ocr_anchor_assigns_whole_pack_as_auto(): void
    {
        $venta = $this->makeInMemoryVenta('33333333C', '2026-07-15');
        $t = Carbon::parse('2026-07-14 18:22:10');

        $orphans = [
            $this->orphanMeta('ventas/pack_precontractual.pdf', 'precontractual', $t, '010'),
            $this->orphanMeta('ventas/pack_dni_anverso.jpg', 'dni_anverso', $t->copy()->addSeconds(5), '010'),
            $this->orphanMeta('ventas/pack_dni_reverso.jpg', 'dni_reverso', $t->copy()->addSeconds(8), '010'),
            $this->orphanMeta('ventas/pack_unknown.pdf', null, $t->copy()->addSeconds(12), '010'),
        ];

        $ocrCalls = 0;
        $matcher = new OrphanDocumentMatcher(
            app(ContractImageExtractor::class),
            function (string $type, string $path) use (&$ocrCalls): array {
                $ocrCalls++;
                if ($path === 'ventas/pack_precontractual.pdf') {
                    return [
                        'dni' => '33333333C',
                        'fecha_venta' => '2026-07-15',
                        'documento_tipo' => 'precontractual',
                    ];
                }

                return [
                    'dni' => '33333333C',
                    'fecha_venta' => '2026-07-15',
                    'documento_tipo' => match (true) {
                        str_contains($path, 'dni_anverso') => 'dni_anverso',
                        str_contains($path, 'dni_reverso') => 'dni_reverso',
                        default => null,
                    },
                ];
            },
        );

        $proposals = $matcher->proposePacks($venta, $orphans, withOcr: true);

        $this->assertGreaterThanOrEqual(1, $ocrCalls);
        $this->assertCount(4, $proposals);
        $this->assertTrue(collect($proposals)->every(fn ($p) => $p['action'] === 'auto'));
        $this->assertTrue(collect($proposals)->every(fn ($p) => $p['score'] >= 90));

        $byField = collect($proposals)->keyBy('field');
        $this->assertSame('ventas/pack_precontractual.pdf', $byField['precontractual']['path']);
        $this->assertSame('ventas/pack_dni_anverso.jpg', $byField['dni_anverso']['path']);
        $this->assertSame('ventas/pack_dni_reverso.jpg', $byField['dni_reverso']['path']);
        $this->assertSame('ventas/pack_unknown.pdf', $byField['documento_titularidad']['path']);
    }

    public function test_propose_packs_ignores_files_outside_window(): void
    {
        $venta = $this->makeInMemoryVenta('44444444D', '2026-07-15');
        $orphans = [
            $this->orphanMeta(
                'ventas/far_precontractual.pdf',
                'precontractual',
                Carbon::parse('2026-06-01 12:00:00'),
            ),
        ];

        $matcher = new OrphanDocumentMatcher(
            app(ContractImageExtractor::class),
            fn (): array => ['dni' => '44444444D', 'fecha_venta' => '2026-07-15'],
        );

        $this->assertSame([], $matcher->proposePacks($venta, $orphans, withOcr: true));
    }

    public function test_propose_packs_rejects_wrong_dni_on_anchor(): void
    {
        $venta = $this->makeInMemoryVenta('55555555E', '2026-07-15');
        $t = Carbon::parse('2026-07-14 18:22:00');
        $orphans = [
            $this->orphanMeta('ventas/bad_precontractual.pdf', 'precontractual', $t),
            $this->orphanMeta('ventas/bad_dni_anverso.jpg', 'dni_anverso', $t),
        ];

        $matcher = new OrphanDocumentMatcher(
            app(ContractImageExtractor::class),
            fn (): array => ['dni' => '99999999Z', 'fecha_venta' => '2026-07-15'],
        );

        $this->assertSame([], $matcher->proposePacks($venta, $orphans, withOcr: true));
    }

    public function test_score_rejects_upload_outside_recovery_window_without_ocr_fecha(): void
    {
        $venta = $this->makeInMemoryVenta('77777777G', '2026-07-15');
        $orphan = $this->orphanMeta(
            'ventas/old_precontractual.pdf',
            'precontractual',
            Carbon::parse('2026-06-01 12:00:00'),
        );

        $score = app(OrphanDocumentMatcher::class)->scoreMatch($venta, $orphan, [
            'dni' => '77777777G',
        ]);

        $this->assertSame(0, $score);
    }

    public function test_apply_pack_fills_empty_slots_without_overwrite(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('apply() requiere MySQL (DB_CONNECTION=mysql).');
        }

        $venta = $this->seedPersistedVenta('66666666F', '2026-07-15');
        $venta->forceFill([
            'precontractual' => null,
            'dni_anverso' => null,
            'dni_reverso' => 'ventas/already_linked_reverso.jpg',
            'otros_documentos' => null,
        ])->saveQuietly();

        Storage::fake('public');
        Storage::disk('public')->put('ventas/pack_precontractual.pdf', 'p');
        Storage::disk('public')->put('ventas/pack_dni_anverso.jpg', 'a');
        Storage::disk('public')->put('ventas/pack_dni_reverso.jpg', 'r');
        Storage::disk('public')->put('ventas/pack_extra.pdf', 'x');

        $matcher = app(OrphanDocumentMatcher::class);
        $result = $matcher->apply([
            [
                'venta_id' => $venta->id,
                'path' => 'ventas/pack_precontractual.pdf',
                'field' => 'precontractual',
                'action' => 'auto',
            ],
            [
                'venta_id' => $venta->id,
                'path' => 'ventas/pack_dni_anverso.jpg',
                'field' => 'dni_anverso',
                'action' => 'auto',
            ],
            [
                'venta_id' => $venta->id,
                'path' => 'ventas/pack_dni_reverso.jpg',
                'field' => 'dni_reverso',
                'action' => 'auto',
            ],
            [
                'venta_id' => $venta->id,
                'path' => 'ventas/pack_extra.pdf',
                'field' => 'otros_documentos',
                'action' => 'auto',
            ],
        ]);

        $this->assertSame(3, $result['applied']);
        $this->assertSame(1, $result['skipped']);

        $fresh = $venta->fresh();
        $this->assertSame('ventas/pack_precontractual.pdf', $fresh->precontractual);
        $this->assertSame('ventas/pack_dni_anverso.jpg', $fresh->dni_anverso);
        $this->assertSame('ventas/already_linked_reverso.jpg', $fresh->dni_reverso);
        $this->assertSame('ventas/pack_extra.pdf', $fresh->otros_documentos);
    }

    /**
     * @return array{path: string, field: ?string, uploaded_at: Carbon, empleado_id: string, uploader_slug: string}
     */
    private function orphanMeta(
        string $path,
        ?string $field,
        Carbon $uploadedAt,
        string $empleadoId = '010',
    ): array {
        return [
            'path' => $path,
            'field' => $field,
            'uploaded_at' => $uploadedAt,
            'empleado_id' => $empleadoId,
            'uploader_slug' => 'test_slug',
        ];
    }

    private function makeInMemoryVenta(string $dni, string $fechaVenta): Venta
    {
        $customer = new Customer([
            'dni' => $dni,
            'first_names' => 'Pack',
            'last_names' => 'Cliente',
        ]);
        $comercial = new User([
            'name' => 'Com',
            'last_name' => 'Pack',
            'empleado_id' => '010',
        ]);

        $venta = new Venta([
            'id' => random_int(900000, 999999),
            'fecha_venta' => $fechaVenta,
            'nro_contr_adm' => 'P'.random_int(1000, 9999),
            'precontractual' => null,
            'dni_anverso' => null,
            'dni_reverso' => null,
            'foto_sorteo' => null,
            'documento_titularidad' => null,
            'nomina' => null,
            'pension' => null,
            'otros_documentos' => null,
            'contrato_firmado' => null,
        ]);
        $venta->exists = false;
        $venta->setRelation('customer', $customer);
        $venta->setRelation('comercial', $comercial);

        return $venta;
    }

    private function seedPersistedVenta(string $dni, string $fechaVenta): Venta
    {
        $user = User::factory()->create([
            'name' => 'Com',
            'last_name' => 'Pack',
            'email' => 'pack-'.Str::random(6).'@test.local',
            'empleado_id' => '010',
        ]);
        $customer = \App\Models\Customer::factory()->create([
            'dni' => $dni,
            'first_names' => 'Pack',
            'last_names' => 'Cliente',
        ]);
        $note = \App\Models\Note::factory()->create([
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
            'nro_contr_adm' => 'P'.random_int(1000, 9999),
            'origen_venta' => 'puerta_fria',
            'precontractual' => null,
            'dni_anverso' => null,
            'dni_reverso' => null,
            'otros_documentos' => null,
        ]);

        return $venta->load(['customer', 'comercial']);
    }
}
