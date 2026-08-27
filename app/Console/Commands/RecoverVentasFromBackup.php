<?php

namespace App\Console\Commands;

use App\Support\VentaBackupRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecoverVentasFromBackup extends Command
{
    protected $signature = 'ventas:recover-from-backup
        {--connection=mysql_backup : Conexión Laravel al dump importado}
        {--nro= : Restaurar solo este nro_contr_adm (ej. 1189)}
        {--from= : Solo ventas con fecha_venta >= YYYY-MM-DD}
        {--limit= : Máximo de contratos a procesar}
        {--dry-run : Listar/simular sin escribir (por defecto si no pasas --execute)}
        {--execute : Escribir en la BD de producción/actual}';

    protected $description = 'Compara contratos del backup con producción y recupera los que falten (por nro_contr_adm).';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $dryRun = ! $this->option('execute');

        try {
            DB::connection($connection)->getPdo();
        } catch (Throwable $e) {
            $this->error("No se pudo conectar a [{$connection}]: {$e->getMessage()}");
            $this->line('1) Crea una BD (ej. ohana_backup) e importa docs/diosmeayuda.sql');
            $this->line('2) Configura DB_BACKUP_* en .env (ver .env.example)');
            $this->line('3) Vuelve a ejecutar este comando con --dry-run');

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $recovery = VentaBackupRecovery::make($connection);

        $this->info($dryRun
            ? 'MODO DRY-RUN (no escribe). Usa --execute para aplicar.'
            : 'MODO EXECUTE: se escribirá en la BD actual.');

        $missing = $recovery->missingVentas(
            nro: $this->option('nro') ?: null,
            fromDate: $this->option('from') ?: null,
            limit: $limit,
        );

        if ($missing->isEmpty()) {
            $this->info('No hay contratos faltantes con esos filtros.');

            return self::SUCCESS;
        }

        $this->warn("Contratos a procesar: {$missing->count()}");

        $rows = [];
        $nroToId = [];
        $counts = ['would_insert' => 0, 'inserted' => 0, 'restored_soft' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($missing as $backupVenta) {
            $result = $recovery->recoverOne($backupVenta, dryRun: $dryRun);
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
            $rows[] = [
                $result['nro'],
                $result['status'],
                $result['message'],
            ];
            if (! empty($result['venta_id'])) {
                $nroToId[$result['nro']] = $result['venta_id'];
            }
        }

        $this->table(['nro_contr_adm', 'estado', 'detalle'], $rows);

        $linked = $recovery->relinkTransactionVentas($nroToId, dryRun: $dryRun);
        $this->line($dryRun
            ? "Vínculos A↔B que se crearían: {$linked}"
            : "Vínculos A↔B creados: {$linked}");

        $this->newLine();
        $this->info('Resumen: '.json_encode($counts));

        if ($dryRun) {
            $this->comment('Para aplicar: php artisan ventas:recover-from-backup --execute');
            $this->comment('Ejemplo un contrato: php artisan ventas:recover-from-backup --nro=1189 --execute');
        }

        return ($counts['error'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
