<?php

namespace App\Console\Commands;

use App\Models\Venta;
use App\Services\ContractRecovery\OrphanDocumentMatcher;
use Illuminate\Console\Command;

/**
 * Propone / aplica re-enganche de docs huérfanos a ventas recuperadas.
 *
 * Dry-run (default):
 *   php artisan recovery:reattach-orphan-docs --nro=1234 --ocr
 *   php artisan recovery:reattach-orphan-docs --from-recovered --month=202601
 *
 * Aplicar matches claros:
 *   php artisan recovery:reattach-orphan-docs --venta=99 --ocr --apply
 */
class ReattachOrphanDocs extends Command
{
    protected $signature = 'recovery:reattach-orphan-docs
        {--venta= : ID de venta}
        {--nro= : Nº contrato admin}
        {--from-recovered : Todas las ventas recuperadas (items added + etiqueta observación)}
        {--month= : Filtrar huérfanos por YYYYMM del nombre de archivo}
        {--ocr : Extraer DNI/Fec.Promo con visión (necesario para auto-match)}
        {--apply : Escribir paths en slots vacíos (solo action=auto)}
        {--output= : CSV de propuestas (default storage/app/recovery/reattach-proposals.csv)}';

    protected $description = 'Re-asocia documentos huérfanos a ventas recuperadas (dry-run por defecto)';

    public function handle(OrphanDocumentMatcher $matcher): int
    {
        $ventaId = $this->option('venta') !== null ? (int) $this->option('venta') : null;
        $nro = $this->option('nro') ? (string) $this->option('nro') : null;
        $fromRecovered = (bool) $this->option('from-recovered');
        $month = $this->option('month') ? (string) $this->option('month') : null;
        $withOcr = (bool) $this->option('ocr');
        $apply = (bool) $this->option('apply');

        if (! $ventaId && blank($nro) && ! $fromRecovered) {
            $this->error('Indica --venta=ID, --nro=… o --from-recovered');

            return self::FAILURE;
        }

        if ($apply && ! $withOcr) {
            $this->error('--apply requiere --ocr para no enlazar a ciegas.');

            return self::FAILURE;
        }

        if ($withOcr && ! filled(config('services.openai.api_key'))) {
            $this->error('Falta OPENAI_API_KEY. Ejecuta sin --ocr o configura la key.');

            return self::FAILURE;
        }

        $ventas = $matcher->resolveTargetVentas($ventaId ?: null, $nro, $fromRecovered);
        if ($ventas === []) {
            $this->warn('No se encontraron ventas objetivo.');

            return self::FAILURE;
        }

        $this->info('Ventas objetivo: '.count($ventas));
        $this->info('Inventariando huérfanos…');
        $orphans = $matcher->listOrphans($month);
        $this->info('Huérfanos candidatos: '.count($orphans));

        $this->info($withOcr ? 'Generando propuestas con OCR…' : 'Generando propuestas (solo ventana de carga)…');
        $proposals = $matcher->propose($ventas, $orphans, $withOcr);

        $out = (string) ($this->option('output')
            ?: storage_path('app/recovery/reattach-proposals-'.now()->format('Ymd-His').'.csv'));
        $dir = dirname($out);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($out, 'w');
        if ($fh === false) {
            $this->error("No se pudo escribir {$out}");

            return self::FAILURE;
        }

        fputcsv($fh, [
            'venta_id',
            'nro_contr_adm',
            'path',
            'field',
            'score',
            'ocr_dni',
            'ocr_fecha',
            'action',
            'reason',
        ]);

        $auto = 0;
        $review = 0;
        $skip = 0;
        foreach ($proposals as $p) {
            fputcsv($fh, [
                $p['venta_id'],
                $p['nro_contr_adm'],
                $p['path'],
                $p['field'],
                $p['score'],
                $p['ocr_dni'],
                $p['ocr_fecha'],
                $p['action'],
                $p['reason'],
            ]);
            match ($p['action']) {
                'auto' => $auto++,
                'review' => $review++,
                default => $skip++,
            };
        }
        fclose($fh);

        $this->table(
            ['auto', 'review', 'skip', 'total'],
            [[$auto, $review, $skip, count($proposals)]]
        );
        $this->info("CSV: {$out}");

        if ($apply) {
            $result = $matcher->apply($proposals);
            $this->info("Aplicados: {$result['applied']} · Omitidos: {$result['skipped']}");
        } else {
            $this->comment('Dry-run: no se escribió en BD. Usa --ocr --apply para enlazar matches claros.');
        }

        return self::SUCCESS;
    }
}
