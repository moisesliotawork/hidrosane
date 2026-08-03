<?php

namespace App\Console\Commands;

use App\Models\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Exporta a tar.gz los documentos enlazados en BD a ventas de un mes (fecha_venta).
 * Uso típico: bajar al Mac todo lo de octubre 2025 referenciado en ventas.
 */
class ExportMonthDocuments extends Command
{
    protected $signature = 'recovery:export-month-documents
        {--month= : Mes YYYYMM (ej. 202510)}
        {--output= : Ruta del .tar.gz (default /tmp/docs_YYYYMM.tar.gz)}
        {--list= : Ruta del listado de paths (default /tmp/docs_YYYYMM.txt)}
        {--only= : Campos documento separados por coma (default todos)}';

    protected $description = 'Empaqueta documentos de ventas de un mes (paths en BD) para descargar.';

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
        $month = preg_replace('/\D+/', '', (string) $this->option('month'));
        if (strlen($month) !== 6) {
            $this->error('Usa --month=YYYYMM (ej. 202510 para octubre 2025).');

            return self::FAILURE;
        }

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

        $this->info('Ventas del mes: '.$ventas->count());

        $publicRoot = Storage::disk('public')->path('');
        $entries = [];
        $missing = 0;

        foreach ($ventas as $venta) {
            foreach ($cols as $col) {
                $raw = $venta->{$col} ?? null;
                if (! filled($raw)) {
                    continue;
                }
                $rel = ltrim((string) $raw, '/');
                $abs = $publicRoot.$rel;
                if (! is_file($abs)) {
                    // paths a veces sin prefijo ventas/
                    $alt = $publicRoot.'ventas/'.basename($rel);
                    if (is_file($alt)) {
                        $rel = 'ventas/'.basename($rel);
                        $abs = $alt;
                    } else {
                        $missing++;
                        $this->warn("Falta en disco: venta {$venta->id} {$col}={$raw}");

                        continue;
                    }
                }
                $entries[$rel] = true;
            }
        }

        $paths = array_keys($entries);
        sort($paths);

        $listPath = (string) ($this->option('list') ?: "/tmp/docs_{$month}.txt");
        $tarPath = (string) ($this->option('output') ?: "/tmp/docs_{$month}.tar.gz");

        file_put_contents($listPath, implode(PHP_EOL, $paths).(count($paths) ? PHP_EOL : ''));

        $this->info('Documentos en BD (paths únicos): '.count($paths));
        $this->info("Faltan en disco: {$missing}");
        $this->info("Lista: {$listPath}");

        if ($paths === []) {
            $this->warn('No hay documentos para empaquetar.');

            return self::SUCCESS;
        }

        $cwd = getcwd();
        chdir($publicRoot);
        $cmd = sprintf(
            'tar -czf %s -T %s 2>&1',
            escapeshellarg($tarPath),
            escapeshellarg($listPath)
        );
        exec($cmd, $output, $code);
        chdir($cwd ?: $publicRoot);

        if ($code !== 0 || ! is_file($tarPath)) {
            $this->error('Falló tar: '.implode(' ', $output));

            return self::FAILURE;
        }

        $this->info('Paquete: '.$tarPath.' ('. $this->humanSize(filesize($tarPath)).')');
        $this->line('En el Mac:');
        $this->line("  mkdir -p ~/Desktop/docs_{$month}_bd && cd ~/Desktop/docs_{$month}_bd");
        $this->line("  scp forge@SERVIDOR:{$tarPath} . && tar -xzf ".basename($tarPath)." && open .");

        return self::SUCCESS;
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
