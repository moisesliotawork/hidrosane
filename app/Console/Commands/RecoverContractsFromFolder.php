<?php

namespace App\Console\Commands;

use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Services\ContractRecovery\ContractFromImageRecovery;
use App\Services\ContractRecovery\ContractImageExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Recuperación batch desde una carpeta de fotos (contratos/albaranes sueltos).
 *
 * Reutiliza EXACTAMENTE la misma lógica de negocio que la página
 * "1. Recuperar contrato" (Analizar documentos → Aceptar). No crea ventas:
 * solo deja registros en `contrato_recovery_items` listos para revisar
 * (o en la tabla de rechazados si el nº de contrato ya existe como venta activa).
 *
 * Uso:
 *   php artisan recovery:batch-from-folder /ruta/a/carpeta --dry-run
 *   php artisan recovery:batch-from-folder /ruta/a/carpeta --state=storage/app/recovery/batch-state.json
 */
class RecoverContractsFromFolder extends Command
{
    protected $signature = 'recovery:batch-from-folder
        {folder : Carpeta con las fotos (jpg/jpeg/png)}
        {--state= : Ruta del archivo JSON de estado (para reanudar sin repetir llamadas a Vision)}
        {--dry-run : No escribe en BD. Solo clasifica, extrae y muestra el resumen}
        {--limit= : Procesar solo las primeras N imágenes (para pruebas)}
        {--force : No pedir confirmación antes de escribir en BD (necesario en procesos no interactivos / nohup)}';

    protected $description = 'Clasifica (documento/captura) y extrae vía OCR fotos sueltas de contratos/albaranes, agrupa por nº de contrato y deja registros en contrato_recovery_items listos para revisar.';

    public function handle(): int
    {
        // Las fotos de móvil pesan ~5-7MB; base64 + payload HTTP superan el límite CLI por defecto.
        @ini_set('memory_limit', '1024M');

        $folder = rtrim((string) $this->argument('folder'), '/');
        if (! is_dir($folder)) {
            $this->error("No existe la carpeta: {$folder}");

            return self::FAILURE;
        }

        $statePath = (string) ($this->option('state') ?: storage_path('app/recovery/batch-state-'.md5($folder).'.json'));
        @mkdir(dirname($statePath), 0755, true);

        $state = is_file($statePath) ? (json_decode((string) file_get_contents($statePath), true) ?: []) : [];
        $state['files'] = $state['files'] ?? [];

        $files = collect(scandir($folder) ?: [])
            ->filter(fn ($f) => preg_match('/\.(jpe?g|png)$/i', $f))
            ->sort(fn ($a, $b) => strnatcmp($a, $b))
            ->values();

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        if ($limit) {
            $files = $files->take($limit);
        }

        $this->info('Archivos encontrados: '.$files->count());

        $extractor = app(ContractImageExtractor::class);
        $apiKey = (string) config('services.openai.api_key', '');

        foreach ($files as $filename) {
            if (isset($state['files'][$filename]) && ($state['files'][$filename]['done'] ?? false)) {
                $this->line("· ya procesado, salto: {$filename}");

                continue;
            }

            $path = $folder.'/'.$filename;
            $this->line("→ clasificando {$filename} ...");

            $exifFixed = null;
            $resized = null;
            $rotated = null;

            try {
                // 1) Corrige el flag EXIF Orientation del original (fotos de móvil).
                //    Si no se hace ANTES de redimensionar, el resize con GD (que no
                //    conserva EXIF) heredaría los píxeles sin corregir y perderíamos
                //    esa información para siempre.
                $mime = mime_content_type($path) ?: 'image/jpeg';
                $exifFixed = $extractor->exifCorrectedCopy($path, $mime);
                $base = $exifFixed ?? $path;

                // 2) Redimensiona para ahorrar tokens de Vision.
                $resized = $this->resizedCopy($base, 1500);
                $base = $resized ?? $base;

                // 3) Clasifica documento/captura.
                ['kind' => $kind] = $this->withRetry(fn () => $this->classify($base));

                // 4) Solo si el paso 1 NO pudo corregir nada por EXIF (es decir, la
                //    foto no tenía flag de orientación) comprobamos con IA si sigue
                //    físicamente girada. Ejecutarlo siempre daba falsos positivos
                //    sobre fotos que el EXIF ya había dejado bien (ver nota en classify()).
                if ($kind === 'documento' && $exifFixed === null) {
                    $rotation = $this->withRetry(fn () => $this->detectRotation($apiKey, $base));
                    if ($rotation !== 0) {
                        $this->line("  detectada rotación residual {$rotation}°, enderezando...");
                        $rotated = $this->rotatedCopy($base, $rotation);
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("  clasificación falló (definitivo): {$e->getMessage()}");
                $this->cleanupTemp($exifFixed);
                $this->cleanupTemp($resized);

                // Error no recuperable (ej. archivo corrupto): lo marcamos "done" para no
                // bloquear el resto del batch, pero queda visible como 'error' en el resumen.
                $state['files'][$filename] = [
                    'filename' => $filename,
                    'kind' => 'error',
                    'suffix' => $this->filenameSuffix($filename),
                    'data' => null,
                    'error' => $e->getMessage(),
                    'done' => true,
                ];
                file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                continue;
            }

            $entry = [
                'filename' => $filename,
                'kind' => $kind,
                'suffix' => $this->filenameSuffix($filename),
                'data' => null,
                'error' => null,
                'done' => true,
            ];

            if ($kind === 'documento') {
                try {
                    $entry['data'] = $this->withRetry(
                        fn () => $extractor->extractOne(ContractImageExtractor::TYPE_OTHER, $rotated ?? $resized ?? $exifFixed ?? $path)
                    );
                    $this->info('  documento OK, nro_contr_adm='.($entry['data']['nro_contr_adm'] ?? 'null').', dni='.($entry['data']['dni'] ?? 'null'));
                } catch (\Throwable $e) {
                    $entry['error'] = $e->getMessage();
                    $entry['done'] = true;
                    $this->warn('  extracción falló (definitivo): '.$e->getMessage());
                }
            } else {
                $this->line("  clasificado como «{$kind}», se excluye del OCR de datos.");
            }

            $this->cleanupTemp($exifFixed);
            $this->cleanupTemp($resized);
            $this->cleanupTemp($rotated);

            $state['files'][$filename] = $entry;
            file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Respeta el límite de tokens/min de OpenAI entre llamadas.
            usleep(600_000);
        }

        $this->newLine();
        $this->info('Estado guardado en: '.$statePath);

        $groups = $this->groupEntries($state['files']);

        $this->newLine();
        $this->info('=== RESUMEN DE GRUPOS (posibles contratos a recuperar) ===');
        $this->table(
            ['Grupo', 'Nº Contrato', 'Cliente', 'DNI', '#Docs', 'Conflictos', 'Archivos'],
            $groups->map(fn ($g) => [
                $g['key'],
                $g['merged']['nro_contr_adm'] ?? '—',
                mb_strimwidth((string) ($g['merged']['cliente_nombre'] ?? '—'), 0, 28, '…'),
                $g['merged']['dni'] ?? '—',
                count($g['files']),
                count($g['merged']['_conflicts'] ?? []),
                implode(', ', $g['files']),
            ])->all()
        );

        $screenshots = collect($state['files'])->where('kind', 'captura')->count();
        $errors = collect($state['files'])->where('kind', 'error')->count();
        $this->info("Capturas de pantalla excluidas: {$screenshots} · Errores de clasificación: {$errors} · Grupos detectados: {$groups->count()}");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('DRY-RUN: no se escribió nada en base de datos.');

            return self::SUCCESS;
        }

        $this->newLine();
        if (! $this->option('force') && ! $this->confirm('¿Crear los registros en contrato_recovery_items para los '.$groups->count().' grupos detectados?', false)) {
            $this->comment('Cancelado por el usuario.');

            return self::SUCCESS;
        }

        $this->persistGroups($groups, $folder);

        return self::SUCCESS;
    }

    protected function filenameSuffix(string $filename): ?string
    {
        return preg_match('/-(\d+)\.\w+$/', $filename, $m) ? $m[1] : null;
    }

    /**
     * Reintenta con backoff ante 429 (rate limit) de OpenAI. Otros errores se relanzan.
     */
    protected function withRetry(callable $fn, int $maxAttempts = 6): mixed
    {
        $attempt = 0;
        $delayMs = 800;

        while (true) {
            $attempt++;

            try {
                return $fn();
            } catch (\Throwable $e) {
                $isRateLimit = str_contains($e->getMessage(), '429')
                    || str_contains($e->getMessage(), 'rate_limit');

                if (! $isRateLimit || $attempt >= $maxAttempts) {
                    throw $e;
                }

                $this->warn("  rate limit, reintentando en {$delayMs}ms (intento {$attempt}/{$maxAttempts})...");
                usleep($delayMs * 1000);
                $delayMs = min($delayMs * 2, 15000);
            }
        }
    }

    /**
     * Copia redimensionada (lado mayor = $maxSide px) para reducir tokens de Vision.
     * Devuelve null si no se pudo redimensionar (se usa el original tal cual).
     */
    protected function resizedCopy(string $absolutePath, int $maxSide): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! $info) {
            return null;
        }

        [$width, $height, $type] = $info;
        if (max($width, $height) <= $maxSide) {
            return null;
        }

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            default => null,
        };

        if (! $src) {
            return null;
        }

        $ratio = $maxSide / max($width, $height);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        $out = sys_get_temp_dir().'/ohana_batch_'.uniqid('', true).'.jpg';
        imagejpeg($dst, $out, 85);
        imagedestroy($src);
        imagedestroy($dst);

        return is_file($out) ? $out : null;
    }

    protected function cleanupTemp(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Clasifica una imagen: "documento" (papel físico: contrato/albarán/DNI)
     * o "captura" (foto de una pantalla/monitor mostrando una app o web).
     *
     * NOTA: NO detecta rotación aquí. Probado en un lote real de 116 fotos: el
     * detector de rotación por IA (aunque "fiable" en pruebas aisladas) dio
     * falsos positivos en ~40 imágenes que el fix de EXIF ya había corregido
     * bien, y al re-rotarlas de más introdujo números de contrato absurdos
     * (peor que no tocarlas). Por eso la detección de rotación residual
     * (detectRotation) solo se invoca desde handle() para las pocas imágenes
     * SIN flag EXIF de por medio (exifCorrectedCopy() devolvió null), que son
     * las únicas donde de verdad puede haber una rotación física sin forma de
     * detectarla por metadatos.
     */
    protected function classify(string $absolutePath): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Falta OPENAI_API_KEY.');
        }

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        $b64 = base64_encode((string) file_get_contents($absolutePath));
        $dataUrl = "data:{$mime};base64,{$b64}";

        $model = (string) config('services.openai.vision_model', 'gpt-4o-mini');

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'max_tokens' => 5,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Responde con UNA sola palabra, sin puntuación: "captura" si la imagen es una foto de una PANTALLA/MONITOR/ORDENADOR mostrando una aplicación web o panel de administración; "documento" si es una foto de un DOCUMENTO DE PAPEL físico (contrato impreso, albarán, DNI, formulario manuscrito).',
                            ],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI HTTP '.$response->status());
        }

        $word = mb_strtolower(trim((string) data_get($response->json(), 'choices.0.message.content', '')));
        $kind = str_contains($word, 'captura') ? 'captura' : (str_contains($word, 'documento') ? 'documento' : 'error');

        return ['kind' => $kind, 'rotation' => 0];
    }

    /**
     * Detecta si hay que girar la foto para leer el texto normalmente.
     * Usa gpt-4o (no -mini): -mini responde "sin rotar" casi siempre, aunque
     * esté girada. detail=low tampoco vale: es barato pero inconsistente entre
     * llamadas idénticas (el downscale agresivo deja poca señal visual).
     * detail=high es consistente (verificado con repeticiones), y aquí ya
     * partimos de la copia redimensionada a 1500px, así que el coste extra es
     * asumible.
     */
    protected function detectRotation(string $apiKey, string $absolutePath): int
    {
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

    /**
     * Copia rotada $degrees grados en sentido horario (usando GD), para
     * enderezar fotos tomadas con el móvil girado antes de mandarlas a Vision.
     */
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

        // imagerotate() gira en sentido ANTIHORARIO, por eso invertimos el ángulo
        // para que $degrees siga significando "sentido horario" de cara al resto del código.
        $rotated = imagerotate($src, -$degrees, 0);
        imagedestroy($src);

        if (! $rotated) {
            return null;
        }

        $out = sys_get_temp_dir().'/ohana_rot_'.uniqid('', true).'.jpg';
        imagejpeg($rotated, $out, 90);
        imagedestroy($rotated);

        return is_file($out) ? $out : null;
    }

    /**
     * Agrupa las entradas ya extraídas por nº de contrato (con fallback al sufijo
     * del nombre de archivo cuando Vision no pudo leer el nº en esa imagen concreta).
     *
     * @param  array<string, array<string, mixed>>  $files
     * @return \Illuminate\Support\Collection<int, array{key: string, files: list<string>, per_document: list<array<string,mixed>>, merged: array<string,mixed>}>
     */
    protected function groupEntries(array $files): \Illuminate\Support\Collection
    {
        $extractor = app(ContractImageExtractor::class);

        // 1) nº de contrato por archivo (si Vision lo leyó)
        $nroPorArchivo = [];
        foreach ($files as $filename => $entry) {
            $nro = $entry['data']['nro_contr_adm'] ?? null;
            if (filled($nro)) {
                $nroPorArchivo[$filename] = (string) $nro;
            }
        }

        // 2) nº "dominante" por sufijo de archivo (para rescatar imágenes sin nº propio, ej. un DNI suelto)
        $nroPorSufijo = [];
        foreach ($files as $filename => $entry) {
            $suffix = $entry['suffix'] ?? null;
            $nro = $nroPorArchivo[$filename] ?? null;
            if ($suffix !== null && $nro !== null && ! isset($nroPorSufijo[$suffix])) {
                $nroPorSufijo[$suffix] = $nro;
            }
        }

        $groups = [];
        foreach ($files as $filename => $entry) {
            if (($entry['kind'] ?? null) !== 'documento' || ! is_array($entry['data'] ?? null)) {
                continue;
            }

            $key = $nroPorArchivo[$filename]
                ?? $nroPorSufijo[$entry['suffix'] ?? ''] ?? null
                ?? 'SIN_NUMERO_'.($entry['suffix'] ?? md5($filename));

            $groups[$key]['files'][] = $filename;
            $groups[$key]['per_document'][] = [
                'type' => ContractImageExtractor::TYPE_OTHER,
                'path' => $filename,
                'data' => $entry['data'],
            ];
        }

        return collect($groups)->map(function ($g, $key) use ($extractor) {
            [$merged] = $extractor->merge($g['per_document']);

            return [
                'key' => $key,
                'files' => $g['files'],
                'per_document' => $g['per_document'],
                'merged' => $merged,
            ];
        })->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string,mixed>>  $groups
     */
    protected function persistGroups(\Illuminate\Support\Collection $groups, string $sourceFolder): void
    {
        $recoverySvc = app(ContractFromImageRecovery::class);
        $extractor = app(ContractImageExtractor::class);
        $created = 0;
        $rejected = 0;
        $skipped = 0;

        foreach ($groups as $g) {
            $merged = $g['merged'];
            $dni = mb_strtoupper(trim((string) ($merged['dni'] ?? '')));
            $nro = trim((string) ($merged['nro_contr_adm'] ?? ''));
            $nombre = trim((string) ($merged['cliente_nombre'] ?? ''));

            // El DNI es el campo que MENOS fiable lee Vision (letra pequeña); exigirlo
            // descartaría de entrada la mayoría de contratos válidos. Solo el nº de
            // contrato y el nombre del cliente son imprescindibles (nº para detectar
            // duplicados, nombre para poder identificar al cliente en la revisión);
            // el DNI, si falta, lo rellena el revisor a mano mirando la foto adjunta.
            $dni = $dni !== '' ? $dni : null;

            if ($nro === '') {
                $this->warn("Grupo {$g['key']}: sin nº de contrato, se omite.");
                $skipped++;

                continue;
            }

            if ($nombre === '') {
                $this->warn("Grupo {$g['key']} (nº {$nro}): sin nombre de cliente, se omite.");
                $skipped++;

                continue;
            }

            // Idempotencia: si el comando se relanza (ej. tras corregir algo y
            // reusar el mismo --state), no duplicar grupos ya volcados antes.
            $alreadyExists = ContratoRecoveryItem::query()
                ->where('nro_contr_adm', $nro)
                ->whereIn('status', [
                    ContratoRecoveryItem::STATUS_PENDING_ADD,
                    ContratoRecoveryItem::STATUS_REJECTED_EXISTS,
                    ContratoRecoveryItem::STATUS_ADDED,
                ])
                ->exists();

            if ($alreadyExists) {
                $this->line("Grupo {$g['key']}: ya existe un recovery item para el nº {$nro}, se omite.");
                $skipped++;

                continue;
            }

            $data = $recoverySvc->ensureRecoveryDefaults($merged);

            // documento_tipo por archivo (leído por Vision) → decide qué slot usará
            // este documento al recuperar el contrato (ver attachDocumentsWithoutOverwrite).
            $tipoPorArchivo = collect($g['per_document'] ?? [])
                ->keyBy('path')
                ->map(fn ($doc) => $extractor->normalizeDocumentoTipo($doc['data']['documento_tipo'] ?? null));

            // Copiar fotos del grupo a la carpeta estable de "accepted"
            $stableDocs = [];
            foreach ($g['files'] as $filename) {
                $from = $sourceFolder.'/'.$filename;
                if (! is_file($from)) {
                    continue;
                }
                $ext = pathinfo($from, PATHINFO_EXTENSION) ?: 'jpg';
                $to = 'contract-recovery/accepted/'.now()->format('YmdHis').'_'.uniqid().'.'.$ext;
                Storage::disk('local')->put($to, (string) file_get_contents($from));

                $tipo = $tipoPorArchivo[$filename] ?? null;
                $docType = $tipo === 'precontractual'
                    ? ContractImageExtractor::TYPE_ALBARAN
                    : ContractImageExtractor::TYPE_OTHER;

                $stableDocs[] = ['type' => $docType, 'path' => $to, 'label' => $filename];
            }

            $customer = $dni === null ? null : Customer::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
                ->orderBy('id')
                ->first();

            $existingVenta = $recoverySvc->findActiveVentaByNro($nro);

            ContratoRecoveryItem::query()->create([
                'status' => $existingVenta
                    ? ContratoRecoveryItem::STATUS_REJECTED_EXISTS
                    : ContratoRecoveryItem::STATUS_PENDING_ADD,
                'documents' => $stableDocs,
                'extracted_json' => $data,
                'reviewed_json' => $data,
                'dni' => $dni,
                'nro_contr_adm' => $nro,
                'cliente_nombre' => $data['cliente_nombre'] ?? null,
                'customer_id' => $customer?->id,
                'venta_id' => $existingVenta?->id,
                'comercial_id' => null,
                'created_by_user_id' => auth()->id(),
                'last_error' => $existingVenta
                    ? "YA EXISTE UN CONTRATO con ese número (venta #{$existingVenta->id})."
                    : null,
            ]);

            if ($existingVenta) {
                $rejected++;
            } else {
                $created++;
            }
        }

        $this->newLine();
        $this->info("Creados pendientes de revisar: {$created} · Rechazados (ya en app): {$rejected} · Omitidos (sin DNI/nº): {$skipped}");
    }
}
