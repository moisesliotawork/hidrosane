<?php

namespace App\Console\Commands;

use App\Models\Venta;
use App\Support\Storage\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Exporta a tar.gz los documentos enlazados en BD a ventas de un mes o un día (fecha_venta).
 *
 *   php artisan recovery:export-month-documents --month=202509
 *   php artisan recovery:export-month-documents --date=2025-09-09
 */
class ExportMonthDocuments extends Command
{
    protected $signature = 'recovery:export-month-documents
        {--month= : Mes YYYYMM (ej. 202509)}
        {--date= : Día concreto YYYY-MM-DD (ej. 2025-09-09); tiene prioridad sobre --month}
        {--output= : Ruta del .tar.gz}
        {--list= : Ruta del listado de paths}
        {--only= : Campos documento separados por coma (default todos)}';

    protected $description = 'Empaqueta documentos de ventas de un mes o un día (paths en BD) para descargar.';

    /** @var list<string> */
    protected array $defaultDocColumns = [
        'precontractual',
        'foto_sorteo',
        'otros_documentos',
        'dni_anverso',
        'dni_reverso',
        'documento_titularidad',
        'nomina',
        'pension',
        'contrato_firmado',
    ];

    public function handle(): int
    {
        $dateOpt = $this->option('date');
        $month = preg_replace('/\D+/', '', (string) $this->option('month'));

        if (filled($dateOpt)) {
            $day = trim((string) $dateOpt);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                $this->error('Usa --date=YYYY-MM-DD (ej. 2025-09-09).');

                return self::FAILURE;
            }
            $from = "{$day} 00:00:00";
            $to = "{$day} 23:59:59";
            $label = str_replace('-', '', $day);
        } elseif (strlen($month) === 6) {
            $year = (int) substr($month, 0, 4);
            $mon = (int) substr($month, 4, 2);
            if ($mon < 1 || $mon > 12) {
                $this->error("Mes inválido: {$month}");

                return self::FAILURE;
            }
            $from = sprintf('%04d-%02d-01 00:00:00', $year, $mon);
            $to = sprintf(
                '%04d-%02d-%02d 23:59:59',
                $year,
                $mon,
                (int) date('t', mktime(0, 0, 0, $mon, 1, $year))
            );
            $label = $month;
        } else {
            $this->error('Indica --date=YYYY-MM-DD o --month=YYYYMM.');

            return self::FAILURE;
        }

        $only = $this->option('only');
        $cols = $this->defaultDocColumns;
        if (filled($only)) {
            $cols = array_values(array_filter(array_map('trim', explode(',', (string) $only))));
        }

        $this->info("Ventas con fecha_venta entre {$from} y {$to}");
        $this->info('Campos: '.implode(', ', $cols));

        $ventas = Venta::withTrashed()
            ->whereBetween('fecha_venta', [$from, $to])
            ->get(array_values(array_unique(array_merge(['id', 'nro_contr_adm', 'fecha_venta'], $cols))));

        $this->info('Ventas del periodo: '.$ventas->count());

        /** @var array<string, string> $entries ruta relativa => disco donde está */
        $entries = [];
        $missing = 0;

        foreach ($ventas as $venta) {
            foreach ($cols as $col) {
                $raw = $venta->{$col} ?? null;
                if (! filled($raw)) {
                    continue;
                }

                $found = DocumentStorage::locate((string) $raw);

                if ($found === null) {
                    // paths a veces sin prefijo ventas/
                    $found = DocumentStorage::locate('ventas/'.basename(str_replace('\\', '/', (string) $raw)));
                }

                if ($found === null) {
                    $missing++;
                    $this->warn("Falta en disco: venta {$venta->id} {$col}={$raw}");

                    continue;
                }

                $entries[$found['path']] = $found['name'];
            }
        }

        ksort($entries);
        $paths = array_keys($entries);

        $listPath = (string) ($this->option('list') ?: "/tmp/docs_{$label}.txt");
        $tarPath = (string) ($this->option('output') ?: "/tmp/docs_{$label}.tar.gz");

        file_put_contents($listPath, implode(PHP_EOL, $paths).(count($paths) ? PHP_EOL : ''));

        $this->info('Documentos en BD (paths únicos): '.count($paths));
        $this->info("Faltan en disco: {$missing}");
        $this->info("Lista: {$listPath}");

        if ($paths === []) {
            $this->warn('No hay documentos para empaquetar.');

            return self::SUCCESS;
        }

        $root = $this->singleLocalRoot($entries);

        if ($root === null) {
            // Al menos un documento vive en un disco remoto: hay que bajarlos a
            // un directorio de staging antes de empaquetar.
            $root = $this->stage($entries, $label);

            if ($root === null) {
                return self::FAILURE;
            }
        }

        try {
            $cwd = getcwd();
            chdir($root);
            $cmd = sprintf(
                'tar -czf %s -T %s 2>&1',
                escapeshellarg($tarPath),
                escapeshellarg($listPath)
            );
            exec($cmd, $output, $code);
            chdir($cwd ?: $root);

            if ($code !== 0 || ! is_file($tarPath)) {
                $this->error('Falló tar: '.implode(' ', $output));

                return self::FAILURE;
            }
        } finally {
            $this->cleanupStaging($label);
        }

        $this->info('Paquete: '.$tarPath.' ('.$this->humanSize((int) filesize($tarPath)).')');
        $this->line('En el Mac:');
        $this->line("  mkdir -p ~/Desktop/docs_{$label}_bd && cd ~/Desktop/docs_{$label}_bd");
        $this->line("  scp forge@SERVIDOR:{$tarPath} . && tar -xzf ".basename($tarPath).' && open .');

        return self::SUCCESS;
    }

    /**
     * Raíz del filesystem si TODOS los documentos están en un mismo disco local.
     *
     * En ese caso se empaqueta directamente desde ahí, como siempre: no tiene
     * sentido copiar gigas de un sitio a otro del mismo disco.
     *
     * @param  array<string, string>  $entries
     */
    protected function singleLocalRoot(array $entries): ?string
    {
        $disks = array_values(array_unique(array_values($entries)));

        if (count($disks) !== 1 || DocumentStorage::driverFor($disks[0]) !== 'local') {
            return null;
        }

        return Storage::disk($disks[0])->path('');
    }

    /**
     * Baja los documentos remotos a un directorio temporal conservando la ruta
     * relativa, para que el tar salga con la misma estructura de siempre.
     *
     * @param  array<string, string>  $entries
     */
    protected function stage(array $entries, string $label): ?string
    {
        $root = $this->stagingPath($label);

        $this->cleanupStaging($label);

        if (! @mkdir($root, 0700, true) && ! is_dir($root)) {
            $this->error("No se pudo crear el directorio temporal {$root}");

            return null;
        }

        $this->info('Descargando '.count($entries).' documentos a '.$root.' …');
        $bar = $this->output->createProgressBar(count($entries));

        foreach ($entries as $rel => $diskName) {
            $target = $root.'/'.$rel;
            $dir = dirname($target);

            if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                $bar->finish();
                $this->newLine();
                $this->error("No se pudo crear {$dir}");

                return null;
            }

            // Se lee del disco donde locate() lo encontró: pasar por
            // DocumentStorage::get() volvería a resolverlo y, en Spaces, eso es
            // una petición extra por documento.
            try {
                $contents = Storage::disk($diskName)->get($rel);
            } catch (\Throwable $e) {
                report($e);
                $contents = null;
            }

            if ($contents === null) {
                $bar->finish();
                $this->newLine();
                $this->error("No se pudo leer {$rel} de {$diskName}");

                return null;
            }

            file_put_contents($target, $contents);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $root;
    }

    protected function stagingPath(string $label): string
    {
        return rtrim(sys_get_temp_dir(), '/')."/export_docs_{$label}";
    }

    protected function cleanupStaging(string $label): void
    {
        $root = $this->stagingPath($label);

        if (! is_dir($root)) {
            return;
        }

        exec(sprintf('rm -rf %s', escapeshellarg($root)));
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $n, $units[$i]);
    }
}
