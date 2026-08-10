<?php

namespace App\Console\Commands;

use App\Models\ContratoRecoveryItem;
use App\Services\ContractRecovery\ContractImageExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Reintenta leer el DNI de recovery items que quedaron sin DNI tras el
 * "Analizar documentos" / batch inicial.
 *
 * Dos mejoras respecto al intento original:
 * 1) El prompt de extracción ahora da instrucciones explícitas de dónde está
 *    la línea "DNI/NIE" en el contrato Ohana (ver ContractImageExtractor::promptFor()).
 * 2) El documento guardado en `documents` es SIEMPRE el fichero original tal
 *    cual se subió (sin la corrección de rotación que se aplicaba solo a una
 *    copia temporal durante el proceso batch). Antes de releer el DNI,
 *    corregimos aquí otra vez esa rotación (EXIF + detección residual por IA),
 *    igual que hace recovery:batch-from-folder.
 *
 * Uso:
 *   php artisan recovery:backfill-dni
 *   php artisan recovery:backfill-dni --ids=46,47,92 --dry-run
 */
class RecoveryBackfillDni extends Command
{
    protected $signature = 'recovery:backfill-dni
        {--ids= : IDs de contrato_recovery_items a reintentar, separados por coma (por defecto: todos los pendientes sin DNI)}
        {--dry-run : No escribe en BD, solo muestra el resultado}';

    protected $description = 'Reintenta leer el DNI (prompt mejorado + corrección de rotación) para recovery items pendientes sin DNI.';

    public function handle(ContractImageExtractor $extractor): int
    {
        @ini_set('memory_limit', '1024M');

        $query = ContratoRecoveryItem::query()
            ->whereIn('status', [ContratoRecoveryItem::STATUS_DRAFT, ContratoRecoveryItem::STATUS_PENDING_ADD])
            ->where(function ($q) {
                $q->whereNull('dni')->orWhere('dni', '');
            });

        if ($ids = $this->option('ids')) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $ids))));
            $query->whereIn('id', $ids);
        }

        $items = $query->orderBy('id')->get();
        $this->info("Registros a reintentar: {$items->count()}");

        $found = 0;
        $stillMissing = 0;

        foreach ($items as $item) {
            $docs = collect($item->documents ?? [])
                ->filter(fn ($d) => is_array($d) && filled($d['path'] ?? null))
                ->values();

            $bestDni = null;

            foreach ($docs as $doc) {
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

                    if (filled($data['dni'] ?? null)) {
                        $bestDni = $data['dni'];
                        $label = $doc['label'] ?? $path;
                        $this->info("  #{$item->id} ({$item->nro_contr_adm}): DNI encontrado en {$label} → {$bestDni}");
                        break;
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

            if ($bestDni !== null) {
                if (! $this->option('dry-run')) {
                    $item->update([
                        'dni' => $bestDni,
                        'extracted_json' => array_merge($item->extracted_json ?? [], ['dni' => $bestDni]),
                        'reviewed_json' => array_merge($item->reviewed_json ?? [], ['dni' => $bestDni]),
                    ]);
                }
                $found++;
            } else {
                $stillMissing++;
                $this->line("  #{$item->id} ({$item->nro_contr_adm}): sigue sin DNI legible, revisar a mano.");
            }
        }

        $this->newLine();
        $this->info("DNI recuperados: {$found} · Siguen sin DNI (revisión manual): {$stillMissing}");

        if ($this->option('dry-run')) {
            $this->comment('DRY-RUN: no se escribió nada en base de datos.');
        }

        return self::SUCCESS;
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

        $out = sys_get_temp_dir().'/ohana_dni_rot_'.uniqid('', true).'.jpg';
        imagejpeg($rotated, $out, 90);
        imagedestroy($rotated);

        return is_file($out) ? $out : null;
    }
}
