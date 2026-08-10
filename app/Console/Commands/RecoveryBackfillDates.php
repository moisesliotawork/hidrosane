<?php

namespace App\Console\Commands;

use App\Models\ContratoRecoveryItem;
use App\Services\ContractRecovery\ContractImageExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Vuelve a leer Fec.Promo./Fec.Entr. (fecha_venta/fecha_entrega) para recovery
 * items cuya fecha quedó mal calculada por el bug de ContractImageExtractor::
 * normalizeDate() previo a esta corrección: una fecha SIN año (ej. "2/7", tal
 * como aparece en los contratos Ohana, día/mes) se interpretaba con
 * Carbon::parse() al estilo americano (mes/día) y con el año en curso, en vez
 * de asumir día/mes + año 2025 de la campaña.
 *
 * Por defecto reprocesa los items cuyo fecha_venta/fecha_entrega guardado cae
 * en un año distinto de 2025 (señal inequívoca del bug, ya que todos los
 * contratos en papel de esta recuperación son de 2025).
 *
 * Uso:
 *   php artisan recovery:backfill-dates --dry-run
 *   php artisan recovery:backfill-dates --ids=31,67
 */
class RecoveryBackfillDates extends Command
{
    protected $signature = 'recovery:backfill-dates
        {--ids= : IDs de contrato_recovery_items a reintentar, separados por coma (por defecto: los que tengan fecha fuera de 2025)}
        {--dry-run : No escribe en BD, solo muestra el resultado}';

    protected $description = 'Corrige fecha_venta/fecha_entrega mal interpretadas (mes/día en vez de día/mes) en recovery items.';

    public function handle(ContractImageExtractor $extractor): int
    {
        @ini_set('memory_limit', '1024M');

        $items = $this->resolveTargetItems();
        $this->info("Registros a revisar: {$items->count()}");

        $fixed = 0;
        $unchanged = 0;

        foreach ($items as $item) {
            $docs = collect($item->documents ?? [])
                ->filter(fn ($d) => is_array($d) && filled($d['path'] ?? null))
                ->values();

            $bestFechaVenta = null;
            $bestFechaEntrega = null;

            foreach ($docs as $doc) {
                if ($bestFechaVenta !== null && $bestFechaEntrega !== null) {
                    break;
                }

                $path = (string) $doc['path'];
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($ext === 'pdf' || ! Storage::disk('local')->exists($path)) {
                    continue;
                }

                $temps = [];

                try {
                    $absolute = Storage::disk('local')->path($path);
                    $mime = mime_content_type($absolute) ?: 'image/jpeg';

                    $exifFixed = $extractor->exifCorrectedCopy($absolute, $mime);
                    if ($exifFixed !== null) {
                        $temps[] = $exifFixed;
                    }
                    $base = $exifFixed ?? $absolute;

                    if ($exifFixed === null) {
                        $rotation = $this->detectRotation($base);
                        if ($rotation !== 0) {
                            $rotated = $this->rotatedCopy($base, $rotation);
                            if ($rotated !== null) {
                                $temps[] = $rotated;
                                $base = $rotated;
                            }
                        }
                    }

                    $type = (string) ($doc['type'] ?? ContractImageExtractor::TYPE_OTHER);
                    $data = $extractor->extractOne($type, $base);

                    if ($bestFechaVenta === null && filled($data['fecha_venta'] ?? null)) {
                        $bestFechaVenta = $data['fecha_venta'];
                    }
                    if ($bestFechaEntrega === null && filled($data['fecha_entrega'] ?? null)) {
                        $bestFechaEntrega = $data['fecha_entrega'];
                    }
                } catch (\Throwable $e) {
                    $this->warn("  #{$item->id}: fallo en {$path}: {$e->getMessage()}");
                } finally {
                    foreach ($temps as $t) {
                        @unlink($t);
                    }
                }

                usleep(500_000);
            }

            $oldFv = $item->extracted_json['fecha_venta'] ?? null;
            $oldFe = $item->extracted_json['fecha_entrega'] ?? null;

            $changed = ($bestFechaVenta !== null && $bestFechaVenta !== $oldFv)
                || ($bestFechaEntrega !== null && $bestFechaEntrega !== $oldFe);

            if (! $changed) {
                $unchanged++;
                $this->line("  #{$item->id} ({$item->nro_contr_adm}): sin cambios (fv={$oldFv} fe={$oldFe}).");
                continue;
            }

            $this->info(
                "  #{$item->id} ({$item->nro_contr_adm}): fecha_venta {$oldFv} → " . ($bestFechaVenta ?? $oldFv)
                . " · fecha_entrega {$oldFe} → " . ($bestFechaEntrega ?? $oldFe)
            );

            if (! $this->option('dry-run')) {
                $item->update([
                    'extracted_json' => array_merge($item->extracted_json ?? [], array_filter([
                        'fecha_venta' => $bestFechaVenta,
                        'fecha_entrega' => $bestFechaEntrega,
                    ], fn ($v) => $v !== null)),
                    'reviewed_json' => array_merge($item->reviewed_json ?? [], array_filter([
                        'fecha_venta' => $bestFechaVenta,
                        'fecha_entrega' => $bestFechaEntrega,
                    ], fn ($v) => $v !== null)),
                ]);
            }

            $fixed++;
        }

        $this->newLine();
        $this->info("Corregidos: {$fixed} · Sin cambios: {$unchanged}");

        if ($this->option('dry-run')) {
            $this->comment('DRY-RUN: no se escribió nada en base de datos.');
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContratoRecoveryItem>
     */
    protected function resolveTargetItems(): \Illuminate\Support\Collection
    {
        if ($ids = $this->option('ids')) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $ids))));

            return ContratoRecoveryItem::query()->whereIn('id', $ids)->orderBy('id')->get();
        }

        return ContratoRecoveryItem::query()
            ->orderBy('id')
            ->get()
            ->filter(function (ContratoRecoveryItem $item): bool {
                $fv = $item->extracted_json['fecha_venta'] ?? null;
                $fe = $item->extracted_json['fecha_entrega'] ?? null;

                return (filled($fv) && ! str_starts_with((string) $fv, '2025'))
                    || (filled($fe) && ! str_starts_with((string) $fe, '2025'));
            })
            ->values();
    }

    /**
     * Igual que RecoverContractsFromFolder::detectRotation(): usa gpt-4o para
     * ver si el texto impreso sigue físicamente girado (fotos sin flag EXIF).
     */
    protected function detectRotation(string $absolutePath): int
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return 0;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        $b64 = base64_encode((string) file_get_contents($absolutePath));
        $dataUrl = "data:{$mime};base64,{$b64}";

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'temperature' => 0,
                'max_tokens' => 10,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Mira el texto impreso en este documento. ¿En qué dirección hay que girar la imagen para poder leer el texto normalmente de izquierda a derecha? '
                                    .'Elige UNA opción y responde solo con su nombre: NONE (ya se lee normal), ROTATE_RIGHT (girar 90° a la derecha/horario), ROTATE_LEFT (girar 90° a la izquierda/antihorario), UPSIDE_DOWN (girar 180°).',
                            ],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return 0;
        }

        $word = mb_strtoupper(trim((string) data_get($response->json(), 'choices.0.message.content', '')));

        return match (true) {
            str_contains($word, 'ROTATE_RIGHT') => 90,
            str_contains($word, 'UPSIDE_DOWN') => 180,
            str_contains($word, 'ROTATE_LEFT') => 270,
            default => 0,
        };
    }

    protected function rotatedCopy(string $absolutePath, int $degrees): ?string
    {
        if (! extension_loaded('gd') || ! in_array($degrees, [90, 180, 270], true)) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! $info) {
            return null;
        }

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            default => null,
        };

        if (! $src) {
            return null;
        }

        $rotated = imagerotate($src, -$degrees, 0);
        imagedestroy($src);

        if (! $rotated) {
            return null;
        }

        $out = sys_get_temp_dir().'/ohana_date_rot_'.uniqid('', true).'.jpg';
        imagejpeg($rotated, $out, 90);
        imagedestroy($rotated);

        return is_file($out) ? $out : null;
    }
}
