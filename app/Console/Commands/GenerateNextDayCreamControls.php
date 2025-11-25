<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CreamDailyControl;
use App\Models\User;
use Carbon\Carbon;

class GenerateNextDayCreamControls extends Command
{
    protected $signature = 'creams:generate-next-day';

    protected $description = 'Genera los registros de CreamDailyControl para el día siguiente de todos los comerciales';

    public function handle(): int
    {
        $dailyQuota = 8;

        $today = Carbon::today();
        $tomorrow = (clone $today)->addDay();

        // Todos los comerciales
        $commercials = User::role('commercial')->get();

        foreach ($commercials as $comercial) {

            // 1) Asegurar que exista el registro de HOY
            $todayControl = CreamDailyControl::firstOrCreate(
                [
                    'comercial_id' => $comercial->id,
                    'date' => $today->toDateString(),
                ],
                [
                    // Si NO existía nada para hoy:
                    // asignadas 5, entregadas 0
                    'assigned' => $dailyQuota,
                    'delivered' => 0,
                ]
            );

            // Forzamos recalcular remaining y next_day_to_assign
            $todayControl->save();

            // 2) Cuántas hay que darle MAÑANA:
            //    según la regla del modelo: next_day_to_assign = 5 - remaining = delivered hoy
            $assignedTomorrow = (int) $todayControl->next_day_to_assign;

            // 3) Crear el registro del DÍA SIGUIENTE (si no existe)
            CreamDailyControl::firstOrCreate(
                [
                    'comercial_id' => $comercial->id,
                    'date' => $tomorrow->toDateString(),
                ],
                [
                    // Lo que se le entrega mañana (nuevo stock)
                    'assigned' => $assignedTomorrow,
                    'delivered' => 0,
                    // remaining y next_day_to_assign se recalculan en saving()
                ]
            );
        }

        $this->info('Registros de CreamDailyControl generados para el día siguiente.');
        return self::SUCCESS;
    }
}
