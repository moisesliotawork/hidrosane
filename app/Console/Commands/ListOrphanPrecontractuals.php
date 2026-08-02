<?php

namespace App\Console\Commands;

use App\Models\Venta;
use App\Services\ContractRecovery\ContractImageExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
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
        $disk = Storage::disk('public');
        $ventasDir = $disk->path('ventas');
        if (! is_dir($ventasDir)) {
            $this->error("No existe {$ventasDir}");

            return self::FAILURE;
        }

        $this->info('Inventariando disco *precontractual*…');
        $onDisk = $this->listPrecontractualOnDisk($ventasDir);
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
                    $absolute = $disk->path(preg_replace('#^public/#', '', $rel));
                    // extractOne espera path relativo usable; pasamos path absoluto vía resolve
                    $data = $extractor->extractOne(ContractImageExtractor::TYPE_ALBARAN, $absolute);
                    $row[4] = (string) ($data['cliente_nombre'] ?? '');
                    $row[5] = (string) ($data['dni'] ?? '');
                    $row[6] = (string) ($data['fecha_venta'] ?? '');
                    $row[7] = (string) ($data['fecha_entrega'] ?? '');
                    $row[8] = (string) ($data['nro_albaran'] ?? '');
                    $row[9] = (string) ($data['comercial_codes'] ?? '');
                    $row[10] = '1';
                    usleep(250_000);
                } catch (Throwable $e) {
                    $row[10] = '0';
                    $row[11] = mb_strimwidth($e->getMessage(), 0, 180, '…');
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
     * @return list<string> paths tipo ventas/….
     */
    protected function listPrecontractualOnDisk(string $ventasDir): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ventasDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (! str_contains(mb_strtolower($name), 'precontractual')) {
                continue;
            }
            $full = $file->getPathname();
            $rel = 'ventas/'.ltrim(str_replace($ventasDir, '', $full), DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);
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
}
