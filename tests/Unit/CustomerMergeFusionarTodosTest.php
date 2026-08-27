<?php

namespace Tests\Unit;

use App\Models\CommercialPhoneLog;
use App\Models\Customer;
use App\Models\CustomerObservation;
use App\Models\Note;
use App\Models\Scopes\NotMergedScope;
use App\Models\User;
use App\Models\Venta;
use App\Services\CustomerDuplicateSearchService;
use App\Services\CustomerMergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerMergeFusionarTodosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fusionar_todos_preserves_notes_ventas_observations_and_customer_data(): void
    {
        $user = User::query()->firstOrFail();
        $sharedSecondary = '663576632';

        $keeper = Customer::factory()->create([
            'first_names' => 'TEST FUSION',
            'last_names' => 'KEEPER MERGE',
            'phone' => '981229229',
            'secondary_phone' => $sharedSecondary,
            'dni' => null,
            'email' => 'keeper@test.com',
            'primary_address' => '',
            'created_at' => now()->subDays(2),
        ]);

        $toDelete = Customer::factory()->create([
            'first_names' => 'TEST FUSION',
            'last_names' => 'KEEPER MERGE',
            'phone' => '981229226',
            'secondary_phone' => $sharedSecondary,
            'dni' => '12345678A',
            'email' => null,
            'primary_address' => 'Calle Fusion 1',
            'created_at' => now()->subDay(),
        ]);

        $keeperNote = Note::factory()->create([
            'customer_id' => $keeper->id,
            'user_id' => $user->id,
            'observations' => 'Nota del keeper',
        ]);

        $deletedNote = Note::factory()->create([
            'customer_id' => $toDelete->id,
            'user_id' => $user->id,
            'observations' => 'Nota del duplicado',
        ]);

        $deletedVentaNote = Note::factory()->create([
            'customer_id' => $toDelete->id,
            'user_id' => $user->id,
        ]);

        $ventaOnKeeper = Venta::create([
            'note_id' => $keeperNote->id,
            'customer_id' => $keeper->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 200,
            'importe_comercial' => 200,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'venta_normal',
        ]);

        $ventaOnDeleted = Venta::create([
            'note_id' => $deletedVentaNote->id,
            'customer_id' => $toDelete->id,
            'comercial_id' => $user->id,
            'fecha_venta' => now(),
            'importe_total' => 350,
            'importe_comercial' => 350,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 6,
            'origen_venta' => 'venta_normal',
        ]);

        $observation = CustomerObservation::create([
            'customer_id' => $toDelete->id,
            'author_id' => $user->id,
            'observation' => 'Observación del duplicado',
        ]);

        $phoneLog = CommercialPhoneLog::create([
            'user_id' => $user->id,
            'customer_id' => $toDelete->id,
            'phone1_commercial' => '600111222',
        ]);

        $recordIds = [
            'keeper_note' => $keeperNote->id,
            'deleted_note' => $deletedNote->id,
            'deleted_venta_note' => $deletedVentaNote->id,
            'keeper_venta' => $ventaOnKeeper->id,
            'deleted_venta' => $ventaOnDeleted->id,
            'observation' => $observation->id,
            'phone_log' => $phoneLog->id,
        ];

        $pairs = collect(CustomerDuplicateSearchService::findAutoMergePairsOfTwo())
            ->filter(fn (array $pair) => in_array($keeper->id, [$pair['keeper_id'], $pair['to_delete_id']], true))
            ->values()
            ->all();

        $this->assertCount(1, $pairs);
        $this->assertSame($keeper->id, $pairs[0]['keeper_id']);
        $this->assertSame($toDelete->id, $pairs[0]['to_delete_id']);

        $result = app(CustomerMergeService::class)->mergePairs([
            [
                'keeper_id' => $pairs[0]['keeper_id'],
                'to_delete_id' => $pairs[0]['to_delete_id'],
            ],
        ], $user->id);

        $this->assertSame(1, $result['merged']);
        $this->assertSame([], $result['failed']);

        $keeperFresh = Customer::findOrFail($keeper->id);
        $toDeleteFresh = Customer::withoutGlobalScope(NotMergedScope::class)->findOrFail($toDelete->id);

        foreach ([
            $recordIds['keeper_note'],
            $recordIds['deleted_note'],
            $recordIds['deleted_venta_note'],
        ] as $noteId) {
            $this->assertDatabaseHas('notes', [
                'id' => $noteId,
                'customer_id' => $keeper->id,
            ]);
        }

        $this->assertSame(3, Note::where('customer_id', $keeper->id)->count());

        foreach ([
            $recordIds['keeper_venta'],
            $recordIds['deleted_venta'],
        ] as $ventaId) {
            $this->assertDatabaseHas('ventas', [
                'id' => $ventaId,
                'customer_id' => $keeper->id,
            ]);
        }

        $this->assertSame(2, Venta::where('customer_id', $keeper->id)->count());
        $this->assertSame(350.0, (float) Venta::findOrFail($recordIds['deleted_venta'])->importe_total);

        $this->assertDatabaseHas('customer_observations', [
            'id' => $recordIds['observation'],
            'customer_id' => $keeper->id,
            'observation' => 'Observación del duplicado',
        ]);

        $this->assertDatabaseHas('commercial_phone_logs', [
            'id' => $recordIds['phone_log'],
            'customer_id' => $keeper->id,
        ]);

        $this->assertSame('12345678A', $keeperFresh->dni);
        $this->assertSame('keeper@test.com', $keeperFresh->email);
        $this->assertSame('Calle Fusion 1', $keeperFresh->primary_address);

        $this->assertSame($keeper->id, $toDeleteFresh->merged_into_id);
        $this->assertNotNull($toDeleteFresh->merged_at);
        $this->assertSame($user->id, $toDeleteFresh->merged_by_user_id);

        $this->assertSame(0, Note::where('customer_id', $toDelete->id)->count());
        $this->assertSame(0, Venta::where('customer_id', $toDelete->id)->count());
        $this->assertSame(0, CustomerObservation::where('customer_id', $toDelete->id)->count());
    }

    public function test_fusionar_todos_merge_pairs_processes_multiple_pairs_independently(): void
    {
        $user = User::query()->firstOrFail();
        $shared = '699888777';

        $pairsData = [];

        foreach (['ALFA UNO', 'BETA DOS'] as $suffix) {
            $keeper = Customer::factory()->create([
                'first_names' => 'BULK',
                'last_names' => $suffix,
                'phone' => '611111111',
                'secondary_phone' => $shared,
                'created_at' => now()->subDays(3),
            ]);

            $toDelete = Customer::factory()->create([
                'first_names' => 'BULK',
                'last_names' => $suffix,
                'phone' => '622222222',
                'secondary_phone' => $shared,
                'created_at' => now()->subDay(),
            ]);

            $note = Note::factory()->create([
                'customer_id' => $toDelete->id,
                'user_id' => $user->id,
            ]);

            $pairsData[] = [
                'keeper_id' => $keeper->id,
                'to_delete_id' => $toDelete->id,
                'note_id' => $note->id,
            ];
        }

        $result = app(CustomerMergeService::class)->mergePairs(
            collect($pairsData)->map(fn (array $pair) => [
                'keeper_id' => $pair['keeper_id'],
                'to_delete_id' => $pair['to_delete_id'],
            ])->all(),
            $user->id,
        );

        $this->assertSame(2, $result['merged']);
        $this->assertSame([], $result['failed']);

        foreach ($pairsData as $pair) {
            $this->assertDatabaseHas('notes', [
                'id' => $pair['note_id'],
                'customer_id' => $pair['keeper_id'],
            ]);

            $this->assertDatabaseHas('customers', [
                'id' => $pair['to_delete_id'],
                'merged_into_id' => $pair['keeper_id'],
            ]);
        }
    }
}
