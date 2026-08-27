<?php

namespace Tests\Unit;

use App\Filament\SuperAdmin\Resources\SuperAsignarResource;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class SuperAsignarBulkAssignmentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_parse_nro_nota_inputs_splits_multiple_values(): void
    {
        $this->assertSame(
            ['04204', '04205', '12345'],
            SuperAsignarResource::parseNroNotaInputs('04204, 04205;12345'),
        );
    }

    public function test_apply_bulk_assignment_updates_all_notes_in_one_transaction(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', Note::businessTimezone()));

        $noteA = Mockery::mock(Note::class)->makePartial();
        $noteA->nro_nota = '04204';
        $noteA->estado_terminal = null;

        $noteB = Mockery::mock(Note::class)->makePartial();
        $noteB->nro_nota = '04205';
        $noteB->estado_terminal = null;

        $noteA->shouldReceive('update')->once()->andReturnTrue();
        $noteB->shouldReceive('update')->once()->andReturnTrue();

        $count = SuperAsignarResource::applyBulkAssignment(
            collect([$noteA, $noteB]),
            [
                'comercial_id' => '__RETEN__',
                'assignment_date' => null,
            ],
        );

        $this->assertSame(2, $count);

        Carbon::setTestNow();
    }
}
