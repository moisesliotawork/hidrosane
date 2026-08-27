<?php

namespace Tests\Unit;

use App\Enums\EstadoTerminal;
use App\Filament\SuperAdmin\Resources\SuperAsignarResource;
use App\Models\Note;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class SuperAsignarRetenAssignmentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reten_assignment_sets_assignment_date_to_today_when_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', Note::businessTimezone()));

        $note = Mockery::mock(Note::class)->makePartial();
        $note->nro_nota = '04204';
        $note->estado_terminal = EstadoTerminal::SIN_ESTADO;

        $note->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $updates): bool {
                return $updates['reten'] === true
                    && $updates['assignment_date'] instanceof Carbon
                    && $updates['assignment_date']->toDateString() === '2026-07-13';
            }))
            ->andReturnTrue();

        SuperAsignarResource::applyAssignment($note, [
            'comercial_id' => '__RETEN__',
            'assignment_date' => null,
        ], notify: false);

        Carbon::setTestNow();
    }

    public function test_reten_assignment_uses_selected_assignment_date(): void
    {
        $note = Mockery::mock(Note::class)->makePartial();
        $note->nro_nota = '04204';
        $note->estado_terminal = EstadoTerminal::SIN_ESTADO;

        $note->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $updates): bool {
                return $updates['reten'] === true
                    && $updates['assignment_date'] instanceof Carbon
                    && $updates['assignment_date']->toDateString() === '2026-07-10';
            }))
            ->andReturnTrue();

        SuperAsignarResource::applyAssignment($note, [
            'comercial_id' => '__RETEN__',
            'assignment_date' => '2026-07-10',
        ], notify: false);
    }
}
