<?php

namespace App\Console\Commands;

use App\Support\RecentSaleAgendaBlocker;
use Illuminate\Console\Command;

class BlockNotesByRecentSalesCommand extends Command
{
    protected $signature = 'notes:block-by-recent-sales';

    protected $description = 'Soft-block open notes whose customer (or phone duplicate) has a recent sale';

    public function handle(): int
    {
        $count = RecentSaleAgendaBlocker::backfillFromRecentVentas();

        $this->info("Notas marcadas con blocked_by_venta_id: {$count}");

        return self::SUCCESS;
    }
}
