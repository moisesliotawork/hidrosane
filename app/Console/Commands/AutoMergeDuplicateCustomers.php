<?php

namespace App\Console\Commands;

use App\Services\CustomerAutoMergeService;
use Illuminate\Console\Command;

class AutoMergeDuplicateCustomers extends Command
{
    protected $signature = 'customers:auto-merge-duplicates';

    protected $description = 'Fusiona automáticamente clientes duplicados (pares con mismo nombre y teléfono compartido).';

    public function handle(CustomerAutoMergeService $autoMergeService): int
    {
        $run = $autoMergeService->run(trigger: 'scheduled');

        $this->info(sprintf(
            'Fusión automática completada: %d cliente(s) fusionado(s), %d error(es).',
            $run->merged_count,
            $run->failed_count,
        ));

        if ($run->failures !== null && $run->failures !== []) {
            foreach ($run->failures as $failure) {
                $this->warn($failure);
            }
        }

        return self::SUCCESS;
    }
}
