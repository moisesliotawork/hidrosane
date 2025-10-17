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
        $cutoff = \Carbon\Carbon::now()->subDays($days);

        $valorSala = \App\Enums\EstadoTerminal::SALA->value;     // 'sala'
        $valorAusente = \App\Enums\EstadoTerminal::AUSENTE->value;  // 'ausente'
        $valorVacio = \App\Enums\EstadoTerminal::SIN_ESTADO->value; // ''

        $base = \Illuminate\Support\Facades\DB::table('notes')
            ->whereNotNull('assignment_date')
            ->where('assignment_date', '<=', $cutoff)
            // excluir las que ya están en SALA
            ->where('estado_terminal', '!=', $valorSala)
            // permitir SOLO: NULL, '', 'ausente'
            ->where(function ($q) use ($valorAusente, $valorVacio) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', $valorVacio)
                    ->orWhere('estado_terminal', "")
                    ->orWhere('estado_terminal', $valorAusente);
            });

        $count = (clone $base)->count();

        $updated = $base->update([
            'estado_terminal' => $valorSala,
            'updated_at' => now(),
        ]);

        $this->info("Corte: {$cutoff->toDateTimeString()} | Candidatas: {$count} | Actualizadas: {$updated}");

        return self::SUCCESS;
    }

}
