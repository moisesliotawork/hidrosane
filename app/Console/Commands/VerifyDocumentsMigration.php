<?php

namespace App\Console\Commands;

use App\Models\ContratoRecoveryItem;
use App\Models\Team;
use App\Models\Venta;
use App\Support\Filament\VentaDocumentUpload;
use App\Support\Storage\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Verifica que todos los documentos referenciados en BD existen en el disco de
 * documentos configurado (Fase 4 de la migración a DigitalOcean Spaces).
 *
 *   php artisan documents:verify
 *   php artisan documents:verify --csv=/tmp/documentos-faltantes.csv
 *
 * Salida distinta de 0 mientras quede algún documento fuera del disco principal,
 * para poder encadenarlo en un script y saber cuándo se puede cerrar el acceso
 * público a public/storage.
 */
class VerifyDocumentsMigration extends Command
{
    protected $signature = 'documents:verify
        {--source=* : ventas, teams y/o recovery (default: todas)}
        {--csv= : Ruta de un CSV con el detalle de lo que no está en el disco principal}
        {--per-object : Comprobar objeto a objeto en vez de listar el disco entero}';

    protected $description = 'Comprueba que los documentos de la BD existen en el disco de documentos (migración a Spaces).';

    /** @var array<string, true> */
    protected array $primaryIndex = [];

    /** @var array<string, true> */
    protected array $fallbackIndex = [];

    protected bool $indexed = false;

    /** @var list<array{source: string, id: int|string, field: string, path: string, status: string}> */
    protected array $problems = [];

    /** @var array<string, array{total: int, ok: int, alt: int, fallback: int, missing: int}> */
    protected array $stats = [];

    public function handle(): int
    {
        $primary = DocumentStorage::diskName();
        $fallback = DocumentStorage::fallbackDiskName();

        $this->info('Disco principal: '.$primary.' ('.(DocumentStorage::driverFor($primary) ?? '?').')');
        $this->info('Disco de respaldo: '.($fallback ?? '—'));

        if (! $this->option('per-object')) {
            $this->buildIndexes($primary, $fallback);
        }

        $sources = array_values(array_filter((array) $this->option('source')));
        $sources = $sources === [] ? ['ventas', 'teams', 'recovery'] : $sources;

        foreach ($sources as $source) {
            match ($source) {
                'ventas' => $this->checkVentas(),
                'teams' => $this->checkTeams(),
                'recovery' => $this->checkRecoveryReferences(),
                default => $this->warn("Fuente desconocida, ignorada: {$source}"),
            };
        }

        return $this->report();
    }

    /**
     * Un solo listado por disco en vez de un exists() por documento: contra
     * Spaces cada exists() es un HEAD, y aquí se comprueban miles de rutas.
     */
    protected function buildIndexes(string $primary, ?string $fallback): void
    {
        $this->line('Indexando el disco principal…');
        $this->primaryIndex = $this->indexOf($primary);
        $this->info('  '.count($this->primaryIndex).' ficheros en '.$primary);

        if ($fallback !== null) {
            $this->line('Indexando el disco de respaldo…');
            $this->fallbackIndex = $this->indexOf($fallback);
            $this->info('  '.count($this->fallbackIndex).' ficheros en '.$fallback);
        }

        $this->indexed = true;
    }

    /**
     * @return array<string, true>
     */
    protected function indexOf(string $disk): array
    {
        $index = [];

        try {
            foreach (Storage::disk($disk)->allFiles() as $path) {
                $normalized = DocumentStorage::normalize($path);

                if ($normalized !== null) {
                    $index[$normalized] = true;
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $this->error("No se pudo listar el disco {$disk}: ".$e->getMessage());
        }

        return $index;
    }

    protected function checkVentas(): void
    {
        $cols = VentaDocumentUpload::recoveryDocumentSlots();

        $total = Venta::withTrashed()->count();
        $this->newLine();
        $this->info("Ventas: {$total} (incluyendo borradas)");
        $bar = $this->output->createProgressBar($total);

        Venta::withTrashed()
            ->select(array_values(array_unique(array_merge(['id'], $cols))))
            ->chunkById(500, function ($ventas) use ($cols, $bar): void {
                foreach ($ventas as $venta) {
                    foreach ($cols as $col) {
                        $this->checkValue('ventas', $venta->id, $col, $venta->{$col} ?? null);
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
    }

    protected function checkTeams(): void
    {
        $this->newLine();
        $this->info('Equipos: '.Team::count());

        Team::select(['id', 'foto'])->chunkById(500, function ($teams): void {
            foreach ($teams as $team) {
                $this->checkValue('teams', $team->id, 'foto', $team->foto);
            }
        });
    }

    protected function checkRecoveryReferences(): void
    {
        $this->newLine();
        $this->info('Fotos de referencia de recuperación: '.ContratoRecoveryItem::count().' items');

        ContratoRecoveryItem::select(['id', 'reference_photos'])
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    foreach ((array) ($item->reference_photos ?? []) as $photo) {
                        $this->checkValue('recovery', $item->id, 'reference_photos', $photo);
                    }
                }
            });
    }

    protected function checkValue(string $source, int|string $id, string $field, mixed $value): void
    {
        if (! filled($value)) {
            return;
        }

        // Algún campo trae un JSON con varios ficheros en vez de una ruta suelta.
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $this->checkValue($source, $id, $field, $item);
                }

                return;
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->checkValue($source, $id, $field, $item);
            }

            return;
        }

        $path = DocumentStorage::normalize((string) $value);

        if ($path === null) {
            return;
        }

        $key = $source.'.'.$field;
        $this->stats[$key] ??= ['total' => 0, 'ok' => 0, 'alt' => 0, 'fallback' => 0, 'missing' => 0];
        $this->stats[$key]['total']++;

        if ($this->existsInPrimary($path)) {
            $this->stats[$key]['ok']++;

            return;
        }

        // El histórico guarda algunas rutas sin el prefijo ventas/.
        $alt = 'ventas/'.basename($path);

        if ($alt !== $path && $this->existsInPrimary($alt)) {
            $this->stats[$key]['alt']++;

            return;
        }

        if ($this->existsInFallback($path) || $this->existsInFallback($alt)) {
            $this->stats[$key]['fallback']++;
            $this->problems[] = compact('source', 'id', 'field', 'path') + ['status' => 'solo_en_fallback'];

            return;
        }

        $this->stats[$key]['missing']++;
        $this->problems[] = compact('source', 'id', 'field', 'path') + ['status' => 'no_encontrado'];
    }

    protected function existsInPrimary(string $path): bool
    {
        if ($this->indexed) {
            return isset($this->primaryIndex[$path]);
        }

        try {
            return DocumentStorage::disk()->exists($path);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function existsInFallback(string $path): bool
    {
        $fallback = DocumentStorage::fallbackDiskName();

        if ($fallback === null) {
            return false;
        }

        if ($this->indexed) {
            return isset($this->fallbackIndex[$path]);
        }

        try {
            return Storage::disk($fallback)->exists($path);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function report(): int
    {
        $this->newLine();

        $rows = [];
        $totals = ['total' => 0, 'ok' => 0, 'alt' => 0, 'fallback' => 0, 'missing' => 0];

        ksort($this->stats);

        foreach ($this->stats as $key => $s) {
            $rows[] = [$key, $s['total'], $s['ok'], $s['alt'], $s['fallback'], $s['missing']];

            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $s[$k];
            }
        }

        $rows[] = ['TOTAL', $totals['total'], $totals['ok'], $totals['alt'], $totals['fallback'], $totals['missing']];

        $this->table(
            ['campo', 'refs', 'en principal', 'ruta alterna', 'solo fallback', 'no encontrado'],
            $rows
        );

        $enPrincipal = $totals['ok'] + $totals['alt'];
        $pct = $totals['total'] > 0 ? round($enPrincipal / $totals['total'] * 100, 2) : 100.0;
        $this->info("En el disco principal: {$enPrincipal}/{$totals['total']} ({$pct} %)");

        $csv = $this->option('csv');

        if (filled($csv)) {
            $this->writeCsv((string) $csv);
        } elseif ($this->problems !== []) {
            foreach (array_slice($this->problems, 0, 20) as $p) {
                $this->warn("[{$p['status']}] {$p['source']} #{$p['id']} {$p['field']} → {$p['path']}");
            }

            if (count($this->problems) > 20) {
                $this->warn('… y '.(count($this->problems) - 20).' más. Usa --csv= para el detalle completo.');
            }
        }

        if ($totals['missing'] > 0) {
            $this->error($totals['missing'].' documentos no están en ningún disco.');

            return self::FAILURE;
        }

        if ($totals['fallback'] > 0) {
            $this->error($totals['fallback'].' documentos siguen sólo en el disco de respaldo: aún no se puede cerrar public/storage.');

            return self::FAILURE;
        }

        $this->info('Todos los documentos de la BD están en el disco principal.');

        return self::SUCCESS;
    }

    protected function writeCsv(string $path): void
    {
        $fh = fopen($path, 'w');

        if ($fh === false) {
            $this->error("No se pudo escribir {$path}");

            return;
        }

        fputcsv($fh, ['status', 'fuente', 'id', 'campo', 'path']);

        foreach ($this->problems as $p) {
            fputcsv($fh, [$p['status'], $p['source'], $p['id'], $p['field'], $p['path']]);
        }

        fclose($fh);

        $this->info('Detalle: '.$path.' ('.count($this->problems).' filas)');
    }
}
