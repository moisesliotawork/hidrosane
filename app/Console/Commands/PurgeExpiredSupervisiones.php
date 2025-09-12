<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Supervision;

class PurgeExpiredSupervisiones extends Command
{
    protected $signature = 'supervisiones:purge-expired {--date=}';
    protected $description = 'Elimina supervisiones cuyo end_date coincide con la fecha indicada (por defecto, hoy).';

    public function handle(): int
    {
        // usa la opción --date=YYYY-MM-DD para pruebas, si no, hoy (timezone de la app)
        $date = $this->option('date') ?: now()->toDateString();

        $this->info("Purga de supervisiones con end_date = {$date}");

        $total = 0;

        // Borrado en chunks para no cargar mucha memoria
        Supervision::whereDate('end_date', '<=', $date)
            ->chunkById(1000, function ($chunk) use (&$total) {
                $ids = $chunk->pluck('id')->all();
                $deleted = Supervision::whereIn('id', $ids)->delete();
                $total += $deleted;
            });

        $this->info("Supervisiones eliminadas: {$total}");

        return self::SUCCESS;
    }
}
