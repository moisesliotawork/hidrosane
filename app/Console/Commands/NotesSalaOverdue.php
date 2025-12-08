<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Enums\EstadoTerminal;
use App\Models\Note;
use App\Models\NoteSalaEvent;

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
        $cutoff = Carbon::now()->subDays($days)->startOfDay();

        $valorSala = EstadoTerminal::SALA->value;       
        $valorAusente = EstadoTerminal::AUSENTE->value;    
        $valorVacio = EstadoTerminal::SIN_ESTADO->value; 

        // 1) Buscar las notas candidatas (IDs)
        $candidates = Note::query()
            ->whereNotNull('assignment_date')
            ->where('assignment_date', '<=', $cutoff)
            ->where(function ($q) use ($valorAusente, $valorVacio) {
                $q->whereNull('estado_terminal')
                    ->orWhereIn('estado_terminal', [$valorVacio, $valorAusente]);
            })
            ->pluck('id')
            ->all();

        $count = count($candidates);

        if ($count === 0) {
            $this->info("No hay notas candidatas para pasar a SALA.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($candidates, $valorSala, $cutoff, $count) {
            $now = now();

            // 2) Actualizar notas a SALA + fechas
            $updated = Note::whereIn('id', $candidates)->update([
                'estado_terminal' => $valorSala,
                'sent_to_sala_at' => $now,
                'fecha_declaracion' => $now,
                'updated_at' => $now,
            ]);

            // 3) Crear histórico: vía 'comando'
            $rows = [];
            foreach ($candidates as $noteId) {
                $rows[] = [
                    'note_id' => $noteId,
                    'sent_by_user_id' => null,    
                    'via' => 'comando',   
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                NoteSalaEvent::insert($rows);
            }

            $this->info("Corte: {$cutoff->toDateTimeString()} | Candidatas: {$count} | Actualizadas: {$updated}");
        });

        return self::SUCCESS;
    }
}
