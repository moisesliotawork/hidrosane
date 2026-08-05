<?php

namespace App\Console\Commands;

use App\Exports\RecoveredContractsExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exporta Excel de contratos recuperados (paso 1).
 * Con --with-orphans incluye candidatos de disco (paso 2).
 *
 * php artisan recovery:export-recovered
 * php artisan recovery:export-recovered --from=2026-07-01 --to=2026-08-04
 * php artisan recovery:export-recovered --with-orphans
 */
class ExportRecoveredContracts extends Command
{
    protected $signature = 'recovery:export-recovered
                            {--from= : Fecha desde Y-m-d}
                            {--to= : Fecha hasta Y-m-d}
                            {--with-orphans : Incluir inventario/candidatos de documentos huérfanos (paso 2)}
                            {--path= : Ruta de salida (.xlsx)}';

    protected $description = 'Excel de contratos recuperados (paso 1; opcionalmente con huérfanos del paso 2)';

    public function handle(): int
    {
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;
        $withOrphans = (bool) $this->option('with-orphans');
        $path = $this->option('path')
            ?: ('recovery/contratos-recuperados-'.now()->format('Ymd-His').'.xlsx');

        $relative = str_starts_with($path, storage_path())
            ? ltrim(str_replace(storage_path('app/'), '', $path), '/')
            : ltrim($path, '/');

        if (! str_ends_with(strtolower($relative), '.xlsx')) {
            $relative .= '.xlsx';
        }

        Excel::store(new RecoveredContractsExport($from, $to, $withOrphans), $relative, 'local');

        $full = storage_path('app/'.$relative);
        $this->info("Excel generado: {$full}".($withOrphans ? ' (con candidatos huérfanos)' : ' (sin inventariar huérfanos)'));

        return self::SUCCESS;
    }
}
