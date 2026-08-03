<?php

namespace App\Console\Commands;

use App\Models\Venta;
use Illuminate\Console\Command;

/**
 * Compara una lista de ficheros (basenames) o una carpeta del servidor
 * contra los paths de documentos en ventas (BD).
 *
 * También lista ventas del mes (--month=YYYYMM) sin precontractual.
 */
class CompareAlbaranesFolder extends Command
{
    protected $signature = 'recovery:compare-albaranes-folder
        {--files-from= : Fichero con un basename por línea (subido desde el Mac)}
        {--folder= : Carpeta absoluta en el servidor con los archivos}
        {--month= : Mes YYYYMM para listar ventas sin precontractual (ej. 202510)}
        {--output= : Ruta CSV (default /tmp/albaranes_comparacion.csv)}';

    protected $description = 'Compara albaranes/contratos de una carpeta o lista vs paths en BD.';

    /** @var list<string> */
    protected array $docColumns = [
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
        $filesFrom = $this->option('files-from');
        $folder = $this->option('folder');
        $month = $this->option('month');

        if (! filled($filesFrom) && ! filled($folder) && ! filled($month)) {
            $this->error('Usa --files-from= y/o --folder= y/o --month=YYYYMM.');

            return self::FAILURE;
        }

        $basenames = [];
        if (filled($filesFrom)) {
            $basenames = array_merge($basenames, $this->loadBasenamesFromList((string) $filesFrom));
        }
        if (filled($folder)) {
            $basenames = array_merge($basenames, $this->loadBasenamesFromFolder((string) $folder));
        }
        $basenames = array_values(array_unique(array_filter($basenames)));

        $this->info('Indexando documentos en BD…');
        $index = $this->buildBasenameIndex();
        $this->info('Basenames distintos en BD: '.count($index));

        $out = (string) ($this->option('output') ?: '/tmp/albaranes_comparacion.csv');
        $fh = fopen($out, 'w');
        if ($fh === false) {
            $this->error("No se pudo escribir {$out}");

            return self::FAILURE;
        }

        fputcsv($fh, [
            'tipo',
            'filename',
            'status',
            'venta_id',
            'campo_bd',
            'path_bd',
            'nro_contr_adm',
            'fecha_venta',
            'cliente_nombre',
            'dni',
        ]);

        $linked = 0;
        $orphan = 0;

        foreach ($basenames as $base) {
            $matches = $index[$base] ?? [];
            if ($matches === []) {
                $orphan++;
                fputcsv($fh, [
                    'archivo_carpeta',
                    $base,
                    'orphan',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ]);

                continue;
            }

            foreach ($matches as $match) {
                $linked++;
                fputcsv($fh, [
                    'archivo_carpeta',
                    $base,
                    'linked',
                    $match['venta_id'],
                    $match['campo_bd'],
                    $match['path_bd'],
                    $match['nro_contr_adm'],
                    $match['fecha_venta'],
                    $match['cliente_nombre'],
                    $match['dni'],
                ]);
            }
        }

        $sinAlbaran = 0;
        if (filled($month)) {
            $month = preg_replace('/\D+/', '', (string) $month);
            if (strlen($month) === 6) {
                $sinAlbaran = $this->appendVentasSinPrecontractual($fh, $month);
            } else {
                $this->warn('Ignoro --month (usa YYYYMM, ej. 202510).');
            }
        }

        fclose($fh);

        $this->newLine();
        $this->info("CSV: {$out}");
        $this->line('Archivos en lista/carpeta: '.count($basenames));
        $this->line("Enlazados (filas): {$linked}");
        $this->line("Huérfanos: {$orphan}");
        if (filled($month) && strlen((string) preg_replace('/\D+/', '', (string) $this->option('month'))) === 6) {
            $this->line("Ventas del mes sin precontractual: {$sinAlbaran}");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function loadBasenamesFromList(string $path): array
    {
        if (! is_file($path)) {
            $this->error("No existe --files-from={$path}");

            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $out[] = basename(str_replace('\\', '/', $line));
        }

        $this->info('Desde lista: '.count($out).' nombres');

        return $out;
    }

    /**
     * @return list<string>
     */
    protected function loadBasenamesFromFolder(string $folder): array
    {
        if (! is_dir($folder)) {
            $this->error("No existe --folder={$folder}");

            return [];
        }

        $out = [];
        foreach (scandir($folder) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $folder.DIRECTORY_SEPARATOR.$name;
            if (is_file($full)) {
                $out[] = $name;
            }
        }

        $this->info('Desde carpeta: '.count($out).' archivos');

        return $out;
    }

    /**
     * @return array<string, list<array{venta_id:int|string,campo_bd:string,path_bd:string,nro_contr_adm:?string,fecha_venta:?string,cliente_nombre:string,dni:string}>>
     */
    protected function buildBasenameIndex(): array
    {
        $index = [];
        $cols = array_merge(['id', 'nro_contr_adm', 'fecha_venta', 'customer_id'], $this->docColumns);

        Venta::withTrashed()
            ->with(['customer:id,first_names,last_names,dni'])
            ->select($cols)
            ->orderBy('id')
            ->chunkById(500, function ($ventas) use (&$index) {
                foreach ($ventas as $venta) {
                    $cliente = trim(
                        (string) ($venta->customer?->first_names ?? '').' '.
                        (string) ($venta->customer?->last_names ?? '')
                    );
                    $dni = (string) ($venta->customer?->dni ?? '');
                    $fecha = $venta->fecha_venta
                        ? $venta->fecha_venta->format('Y-m-d')
                        : '';

                    foreach ($this->docColumns as $col) {
                        $raw = $venta->{$col} ?? null;
                        if (! filled($raw)) {
                            continue;
                        }
                        $path = ltrim((string) $raw, '/');
                        $base = basename($path);
                        $row = [
                            'venta_id' => $venta->id,
                            'campo_bd' => $col,
                            'path_bd' => $path,
                            'nro_contr_adm' => $venta->nro_contr_adm,
                            'fecha_venta' => $fecha,
                            'cliente_nombre' => $cliente,
                            'dni' => $dni,
                        ];
                        $index[$base][] = $row;
                    }
                }
            });

        return $index;
    }

    /**
     * @param  resource  $fh
     */
    protected function appendVentasSinPrecontractual($fh, string $month): int
    {
        $year = (int) substr($month, 0, 4);
        $mon = (int) substr($month, 4, 2);
        $from = sprintf('%04d-%02d-01 00:00:00', $year, $mon);
        $to = sprintf(
            '%04d-%02d-%02d 23:59:59',
            $year,
            $mon,
            (int) date('t', mktime(0, 0, 0, $mon, 1, $year))
        );

        $count = 0;
        Venta::withTrashed()
            ->with(['customer:id,first_names,last_names,dni'])
            ->whereBetween('fecha_venta', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('precontractual')->orWhere('precontractual', '');
            })
            ->orderBy('id')
            ->each(function (Venta $venta) use ($fh, &$count) {
                $count++;
                $cliente = trim(
                    (string) ($venta->customer?->first_names ?? '').' '.
                    (string) ($venta->customer?->last_names ?? '')
                );
                fputcsv($fh, [
                    'venta_sin_precontractual',
                    '',
                    'sin_albaran',
                    $venta->id,
                    'precontractual',
                    '',
                    $venta->nro_contr_adm,
                    $venta->fecha_venta?->format('Y-m-d') ?? '',
                    $cliente,
                    (string) ($venta->customer?->dni ?? ''),
                ]);
            });

        return $count;
    }
}
