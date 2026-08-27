<?php

namespace App\Console\Commands;

use App\Services\ContractRecovery\LedgerSheetExtractor;
use App\Support\LedgerNameMatcher;
use Illuminate\Console\Command;
use Throwable;

/**
 * OCR de hojas manuscritas de un mes y cruce vs customers/ventas.
 *
 * Ejemplo:
 * php artisan recovery:match-ledger-month \
 *   --folder="storage/app/recovery/mayo 26/jpg" \
 *   --month=202505
 */
class MatchLedgerMonth extends Command
{
    protected $signature = 'recovery:match-ledger-month
        {--folder= : Carpeta con JPG/PNG de hojas}
        {--month= : Mes YYYYMM (ej. 202505)}
        {--limit= : Máximo de imágenes}
        {--sleep=1.5 : Pausa entre OCR (segundos)}
        {--from-csv= : Rematch BD desde CSV OCR (sin llamar a OpenAI)}
        {--output= : CSV de salida}';

    protected $description = 'Cruza listado manuscrito (hojas) con ventas del mes → CSV faltantes.';

    public function handle(LedgerSheetExtractor $extractor): int
    {
        $month = preg_replace('/\D+/', '', (string) ($this->option('month') ?: '')) ?? '';
        if (strlen($month) !== 6) {
            $this->error('Requiere --month=YYYYMM');

            return self::FAILURE;
        }

        $fromCsv = (string) ($this->option('from-csv') ?: '');
        if ($fromCsv !== '') {
            return $this->rematchFromCsv($fromCsv, $month);
        }

        $folder = (string) ($this->option('folder') ?: '');
        if ($folder === '') {
            $this->error('Requiere --folder=... o --from-csv=...');

            return self::FAILURE;
        }

        if (! is_dir($folder)) {
            $alt = base_path($folder);
            if (is_dir($alt)) {
                $folder = $alt;
            } else {
                $this->error("No existe la carpeta: {$folder}");

                return self::FAILURE;
            }
        }

        if (! filled(config('services.openai.api_key'))) {
            $this->error('Falta OPENAI_API_KEY.');

            return self::FAILURE;
        }

        $files = $this->listImages($folder);
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        if ($limit !== null && $limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        if ($files === []) {
            $this->error('No hay JPG/PNG en la carpeta.');

            return self::FAILURE;
        }

        $out = (string) ($this->option('output')
            ?: storage_path('app/recovery/ledger-match-'.$month.'.csv'));
        @mkdir(dirname($out), 0755, true);

        $fh = fopen($out, 'w');
        if ($fh === false) {
            $this->error("No se pudo escribir {$out}");

            return self::FAILURE;
        }

        fputcsv($fh, $this->csvHeader());

        $sleep = (float) ($this->option('sleep') ?: 1.5);
        $counts = $this->emptyCounts();

        $this->info('Imágenes: '.count($files).' | mes: '.$month);
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $file) {
            $base = basename($file);
            try {
                $extracted = $extractor->extract($file);
                foreach ($extracted['lines'] as $line) {
                    $this->writeMatchedRow($fh, $counts, $month, [
                        'source_file' => $base,
                        'line_no' => $line['line_no'],
                        'cliente_nombre' => $line['cliente_nombre'],
                        'comerciales' => $line['comerciales'],
                        'importe' => $line['importe'],
                        'estado' => $line['estado'],
                        'raw_text' => $line['raw_text'],
                        'ocr_error' => '',
                    ]);
                }
            } catch (Throwable $e) {
                $counts['ocr_error']++;
                fputcsv($fh, [
                    $base, '', '', '', '', '', '', 'ocr_error',
                    '', '', '', '', '', '', '', $e->getMessage(),
                ]);
                $this->newLine();
                $this->warn("OCR falló en {$base}: ".$e->getMessage());
            }

            $bar->advance();
            if ($sleep > 0) {
                usleep((int) round($sleep * 1_000_000));
            }
        }

        $bar->finish();
        $this->newLine(2);
        fclose($fh);

        $this->printSummary($counts, $out);

        return self::SUCCESS;
    }

    protected function rematchFromCsv(string $fromCsv, string $month): int
    {
        if (! is_file($fromCsv)) {
            $alt = base_path($fromCsv);
            if (is_file($alt)) {
                $fromCsv = $alt;
            } else {
                $this->error("No existe CSV: {$fromCsv}");

                return self::FAILURE;
            }
        }

        $in = fopen($fromCsv, 'r');
        if ($in === false) {
            $this->error("No se pudo leer {$fromCsv}");

            return self::FAILURE;
        }

        $header = fgetcsv($in);
        if ($header === false) {
            fclose($in);
            $this->error('CSV vacío');

            return self::FAILURE;
        }

        $out = (string) ($this->option('output')
            ?: storage_path('app/recovery/ledger-match-'.$month.'-prod.csv'));
        @mkdir(dirname($out), 0755, true);
        $fh = fopen($out, 'w');
        if ($fh === false) {
            fclose($in);
            $this->error("No se pudo escribir {$out}");

            return self::FAILURE;
        }

        fputcsv($fh, $this->csvHeader());
        $counts = $this->emptyCounts();
        $idx = array_flip($header);

        while (($row = fgetcsv($in)) !== false) {
            if (($row[$idx['status'] ?? -1] ?? '') === 'ocr_error') {
                $counts['ocr_error']++;
                fputcsv($fh, $row);

                continue;
            }

            $importeRaw = $row[$idx['importe_ocr'] ?? -1] ?? '';
            $importe = ($importeRaw === '' || $importeRaw === null) ? null : (float) $importeRaw;

            $this->writeMatchedRow($fh, $counts, $month, [
                'source_file' => $row[$idx['source_file'] ?? 0] ?? '',
                'line_no' => $row[$idx['line_no'] ?? 1] ?? '',
                'cliente_nombre' => $row[$idx['cliente_nombre_ocr'] ?? 2] ?? '',
                'comerciales' => $row[$idx['comerciales_ocr'] ?? 3] ?? '',
                'importe' => $importe,
                'estado' => $row[$idx['estado_ocr'] ?? 5] ?? '',
                'raw_text' => $row[$idx['raw_text'] ?? 14] ?? '',
                'ocr_error' => '',
            ]);
        }

        fclose($in);
        fclose($fh);
        $this->printSummary($counts, $out);

        return self::SUCCESS;
    }

    /**
     * @param  resource  $fh
     * @param  array<string, int>  $counts
     * @param  array{
     *   source_file: string,
     *   line_no: mixed,
     *   cliente_nombre: string,
     *   comerciales: string,
     *   importe: float|null,
     *   estado: string,
     *   raw_text: string,
     *   ocr_error: string
     * }  $line
     */
    protected function writeMatchedRow($fh, array &$counts, string $month, array $line): void
    {
        $espera = LedgerNameMatcher::expectsVenta((string) $line['estado'], $line['importe']);

        if (! $espera) {
            $status = 'nulo';
            $match = [
                'status' => 'nulo',
                'customer_id' => null,
                'customer_name' => null,
                'venta_id' => null,
                'nro_contr_adm' => null,
                'venta_deleted' => null,
                'candidates' => '',
            ];
        } else {
            $match = LedgerNameMatcher::match(
                (string) $line['cliente_nombre'],
                $month,
                $line['importe']
            );
            $status = $match['status'];
        }

        $counts[$status] = ($counts[$status] ?? 0) + 1;

        fputcsv($fh, [
            $line['source_file'],
            $line['line_no'],
            $line['cliente_nombre'],
            $line['comerciales'],
            $line['importe'],
            $line['estado'],
            $espera ? 1 : 0,
            $status,
            $match['customer_id'],
            $match['customer_name'],
            $match['venta_id'],
            $match['nro_contr_adm'],
            $match['venta_deleted'] === null ? '' : ($match['venta_deleted'] ? 1 : 0),
            $match['candidates'],
            $line['raw_text'],
            $line['ocr_error'],
        ]);
    }

    /** @return list<string> */
    protected function csvHeader(): array
    {
        return [
            'source_file',
            'line_no',
            'cliente_nombre_ocr',
            'comerciales_ocr',
            'importe_ocr',
            'estado_ocr',
            'espera_venta',
            'status',
            'customer_id',
            'customer_name_bd',
            'venta_id',
            'nro_contr_adm',
            'venta_deleted',
            'candidates',
            'raw_text',
            'ocr_error',
        ];
    }

    /** @return array<string, int> */
    protected function emptyCounts(): array
    {
        return [
            'nulo' => 0,
            'match' => 0,
            'cliente_sin_venta' => 0,
            'sin_cliente' => 0,
            'ambiguo' => 0,
            'ocr_error' => 0,
        ];
    }

    /** @param  array<string, int>  $counts */
    protected function printSummary(array $counts, string $out): void
    {
        $this->table(
            ['status', 'count'],
            collect($counts)->map(fn ($c, $k) => [$k, $c])->values()->all()
        );
        $this->info("CSV: {$out}");
    }

    /**
     * @return list<string>
     */
    protected function listImages(string $folder): array
    {
        $files = [];
        foreach (scandir($folder) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            $files[] = $folder.DIRECTORY_SEPARATOR.$name;
        }
        sort($files);

        return $files;
    }
}
