<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Enums\EstadoTerminal;

class NotesSalaOverdue extends Command
{
    /**
     * Nombre del comando.
     */
    protected $signature = 'notes:sala-overdue {--days=5 : Días de antigüedad de assignment_date}';

    /**
     * Descripción.
     */
    protected $description = 'Pasa a SALA las notas con assignment_date más antiguo que N días (por defecto 5).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        // Importante: los updates masivos NO disparan mutators, así que usamos el string del enum.
        $valorSala = EstadoTerminal::SALA->value; // 'sala'

        // Evitamos tocar las que ya están en SALA o que no tienen fecha
        $query = DB::table('notes')
            ->whereNotNull('assignment_date')
            ->where('assignment_date', '<=', $cutoff)
            ->where('estado_terminal', '!=', $valorSala);

        $count = (clone $query)->count();

        // Actualizamos estado y updated_at
        $updated = $query->update([
            'estado_terminal' => $valorSala,
            'updated_at'      => now(),
        ]);

        $this->info("Corte: {$cutoff->toDateTimeString()} | Candidatas: {$count} | Actualizadas: {$updated}");

        return self::SUCCESS;
    }
}
