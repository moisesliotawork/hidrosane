<?php

namespace Tests\Unit;

use App\Models\ContratoMesBaseline;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\ContratosPorMesStats;
use App\Support\VentaSoftDelete;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContratosPorMesStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_reduces_month_total_and_shows_negative_variation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => Carbon::parse('2026-08-10 11:00:00'),
            'importe_total' => 1000,
            'importe_comercial' => 1000,
            'modalidad_pago' => 'Contado',
            'num_cuotas' => 1,
            'nro_contr_adm' => 'DEMVAR1',
            'origen_venta' => 'venta_normal',
        ]);

        // Segunda venta en el mismo mes (sigue activa tras el soft-delete)
        Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'fecha_venta' => Carbon::parse('2026-08-12 11:00:00'),
            'importe_total' => 1100,
            'importe_comercial' => 1100,
            'modalidad_pago' => 'Contado',
            'num_cuotas' => 1,
            'nro_contr_adm' => 'DEMVAR2',
            'origen_venta' => 'venta_normal',
        ]);

        ContratosPorMesStats::freezeBaselinesToCurrent();

        $before = ContratosPorMesStats::rows()->firstWhere('mes_key', '2026-08');
        $this->assertNotNull($before);
        $this->assertSame(2, (int) $before->total);
        $this->assertSame(2, (int) $before->baseline_total);
        $this->assertSame(0, (int) $before->variacion);

        $this->actingAs($user);
        VentaSoftDelete::delete($venta->fresh());

        $after = ContratosPorMesStats::rows()->firstWhere('mes_key', '2026-08');
        $this->assertNotNull($after);
        $this->assertSame(1, (int) $after->total);
        $this->assertSame(2, (int) $after->baseline_total);
        $this->assertSame(-1, (int) $after->variacion);

        $baseline = ContratoMesBaseline::query()->where('mes_key', '2026-08')->first();
        $this->assertSame(2, (int) $baseline->baseline_total);

        Carbon::setTestNow();
    }
}
