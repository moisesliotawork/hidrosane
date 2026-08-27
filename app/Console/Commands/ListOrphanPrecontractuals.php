<?php

namespace App\Console\Commands;

use App\Models\Venta;
use App\Services\ContractRecovery\ContractImageExtractor;
use App\Support\Storage\DocumentStorage;
use Illuminate\Console\Command;
use Throwable;

/**
 * Lista albaranes/precontractuales en disco no enlazados a ninguna venta.
 * Sin --ocr: solo metadatos del nombre (fecha carga, comercial).
 * Con --ocr: añade DNI, cliente, Fec.Promo vía OpenAI (misma API que recuperación).
 */
class ListOrphanPrecontractuals extends Command
{
    protected $signature = 'recovery:list-orphan-precontractuals
        {--ocr : Extraer DNI/nombre/Fec.Promo con visión OpenAI}
        {--limit= : Máximo de ficheros a procesar}
        {--month= : Filtrar por YYYYMM del nombre (ej. 202601)}
        {--include-pages : Incluir archivos .pages}
        {--output= : Ruta CSV (default storage/app/orphan-precontractuals.csv)}';

    protected $description = 'CSV de precontractuales huérfanos (disco vs BD) antes de recuperar.';

    public function handle(ContractImageExtractor $extractor): int
    {
        $this->info('Inventariando disco *precontractual*…');

        $files = DocumentStorage::allFiles('ventas');

        if ($files === []) {
            $this->error('No hay ficheros bajo ventas/ en el disco "'.DocumentStorage::diskName().'".');

            return self::FAILURE;
        }

        $onDisk = $this->filterPrecontractuals($files);
        $this->info('En disco: '.count($onDisk));

        $this->info('Leyendo paths de BD…');
        $inDb = Venta::withTrashed()
            ->whereNotNull('precontractual')
            ->where('precontractual', '!=', '')
            ->pluck('precontractual')
            ->map(fn ($p) => ltrim((string) $p, '/'))
            ->unique()
            ->all();
        $inDbFlip = array_fill_keys($inDb, true);
        $this->info('En BD: '.count($inDb));

        $orphans = array_values(array_filter($onDisk, fn (string $rel) => ! isset($inDbFlip[$rel])));

        if (! $this->option('include-pages')) {
            $orphans = array_values(array_filter(
                $orphans,
                fn (string $rel) => ! str_ends_with(mb_strtolower($rel), '.pages')
            ));
        }

        $month = $this->option('month');
        if (filled($month)) {
            $month = preg_replace('/\D+/', '', (string) $month);
            $orphans = array_values(array_filter(
                $orphans,
                fn (string $rel) => (bool) preg_match('#/'.$month.'\d{2}_#', $rel)
                    || (bool) preg_match('#^ventas/'.$month.'\d{2}_#', $rel)
            ));
            $this->info("Filtro mes {$month}: ".count($orphans));
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        if ($limit !== null && $limit > 0) {
            $orphans = array_slice($orphans, 0, $limit);
        }

        $doOcr = (bool) $this->option('ocr');
        if ($doOcr && ! filled(config('services.openai.api_key'))) {
            $this->error('Falta OPENAI_API_KEY. Genera el CSV sin --ocr o configura la key.');

            return self::FAILURE;
        }

        $out = (string) ($this->option('output') ?: storage_path('app/orphan-precontractuals.csv'));
        $fh = fopen($out, 'w');
        if ($fh === false) {
            $this->error("No se pudo escribir {$out}");

            return self::FAILURE;
        }

        fputcsv($fh, [
            'path',
            'fecha_carga_archivo',
            'empleado_id_uploader',
            'uploader_slug',
            'cliente_nombre',
            'dni',
            'fecha_promo',
            'fecha_entrega',
            'nro_albaran',
            'comercial_codes',
            'ocr_ok',
            'ocr_error',
        ]);

        $bar = $this->output->createProgressBar(count($orphans));
        $bar->start();

        foreach ($orphans as $rel) {
            $meta = $this->parseFilename($rel);
            $row = [
                $rel,
                $meta['uploaded_at'],
                $meta['empleado_id'],
                $meta['uploader_slug'],
                '',
                '',
                '',
                '',
                '',
                '',
                $doOcr ? '0' : '',
                '',
            ];

            if ($doOcr) {
                try {
                    // Ruta relativa al disco de documentos: extractOne() la
                    // resuelve por DocumentStorage y, si vive en Spaces, se la
                    // baja a un temporal que borra al terminar.
                    // PDF → JPG (1ª página) dentro de extractOne
                    $data = $this->extractWithRateLimitRetry($extractor, $rel);
                    $row[4] = (string) ($data['cliente_nombre'] ?? '');
                    $row[5] = (string) ($data['dni'] ?? '');
                    $row[6] = (string) ($data['fecha_venta'] ?? '');
                    $row[7] = (string) ($data['fecha_entrega'] ?? '');
                    $row[8] = (string) ($data['nro_albaran'] ?? '');
                    $row[9] = (string) ($data['comercial_codes'] ?? '');
                    $row[10] = '1';
                    usleep(800_000);
                } catch (Throwable $e) {
                    $row[10] = '0';
                    $row[11] = mb_strimwidth($e->getMessage(), 0, 180, '…');
                    usleep(400_000);
                }
            }

            fputcsv($fh, $row);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        fclose($fh);

        $this->info('CSV: '.$out);
        $this->line('Filas: '.count($orphans).($doOcr ? ' (con OCR)' : ' (solo metadatos de nombre; usa --ocr para DNI/cliente/Fec.Promo)'));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $files  paths relativos al disco de documentos
     * @return list<string> paths tipo ventas/….
     */
    protected function filterPrecontractuals(array $files): array
    {
        $out = [];
        foreach ($files as $rel) {
            $rel = ltrim(str_replace('\\', '/', $rel), '/');
            if (! str_contains(mb_strtolower(basename($rel)), 'precontractual')) {
                continue;
            }
            $out[] = $rel;
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @return array{uploaded_at: string, empleado_id: string, uploader_slug: string}
     */
    protected function parseFilename(string $rel): array
    {
        $base = basename($rel);
        // 20260105_090221_911_adm_carolina_precontractual.pdf
        if (preg_match('/^(\d{8})_(\d{6})_([^_]+)_(.+)_precontractual\./i', $base, $m)) {
            $date = $m[1];
            $time = $m[2];
            $uploaded = sprintf(
                '%s-%s-%s %s:%s:%s',
                substr($date, 0, 4),
                substr($date, 4, 2),
                substr($date, 6, 2),
                substr($time, 0, 2),
                substr($time, 2, 2),
                substr($time, 4, 2),
            );

            return [
                'uploaded_at' => $uploaded,
                'empleado_id' => $m[3],
                'uploader_slug' => $m[4],
            ];
        }

        return [
            'uploaded_at' => '',
            'empleado_id' => '',
            'uploader_slug' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractWithRateLimitRetry(ContractImageExtractor $extractor, string $path): array
    {
        $attempts = 0;
        while (true) {
            try {
                return $extractor->extractOne(ContractImageExtractor::TYPE_ALBARAN, $path);
            } catch (Throwable $e) {
                $attempts++;
                $msg = $e->getMessage();
                $is429 = str_contains($msg, 'HTTP 429') || str_contains($msg, 'Rate limit');
                if (! $is429 || $attempts >= 3) {
                    throw $e;
                }
                $this->warn("Rate limit; espera 45s (intento {$attempts}/3)…");
                sleep(45);
            }
        }
    }
}
