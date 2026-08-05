<?php

namespace App\Console\Commands;

use App\Models\Venta;
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

        $this->info('Paquete: '.$tarPath.' ('.$this->humanSize(filesize($tarPath)).')');
        $this->line('En el Mac:');
        $this->line("  mkdir -p ~/Desktop/docs_{$label}_bd && cd ~/Desktop/docs_{$label}_bd");
        $this->line("  scp forge@SERVIDOR:{$tarPath} . && tar -xzf ".basename($tarPath).' && open .');

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
