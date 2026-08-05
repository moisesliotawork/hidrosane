<?php

namespace App\Services\ContractRecovery;

use App\Models\ContratoRecoveryItem;
use App\Models\Venta;
use App\Support\Filament\VentaDocumentUpload;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Propone (y opcionalmente aplica) el re-enganche de documentos huérfanos
 * en disco público a ventas recuperadas, por DNI OCR + Fec.Promo ≈ fecha_venta.
 *
 * Contempla todos los documentos del formulario de creación de venta
 * ({@see VentaDocumentUpload::creationFormDocumentFields()}) más contrato_firmado.
 */
final class OrphanDocumentMatcher
{
    public const UPLOAD_WINDOW_DAYS = 45;

    public const FECHA_PROMO_TOLERANCE_DAYS = 1;

    /**
     * Slots del formulario de creación + contrato_firmado.
     *
     * @return list<string>
     */
    public static function documentFields(): array
    {
        return VentaDocumentUpload::recoveryDocumentSlots();
    }

    public function __construct(
        protected ContractImageExtractor $extractor,
        /** @var (callable(string $type, string $path): array<string, mixed>)|null */
        protected $ocrExtractor = null,
    ) {}

    /**
     * Paths en public/ventas no referenciados por ninguna venta (incl. soft-deleted).
     *
     * @return list<array{
     *     path: string,
     *     field: string,
     *     uploaded_at: ?Carbon,
     *     empleado_id: string,
     *     uploader_slug: string
     * }>
     */
    public function listOrphans(?string $monthYyyymm = null): array
    {
        $disk = Storage::disk('public');
        $ventasDir = $disk->path('ventas');
        if (! is_dir($ventasDir)) {
            return [];
        }

        $linked = $this->allLinkedDocumentPaths();
        $orphans = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ventasDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();
            if (str_ends_with(mb_strtolower($name), '.pages')) {
                continue;
            }

            $meta = $this->parseFilename($name);
            if ($meta['field'] === null) {
                continue;
            }

            $full = $file->getPathname();
            $rel = 'ventas/'.ltrim(str_replace($ventasDir, '', $full), DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);

            if (isset($linked[$rel]) || isset($linked[ltrim($rel, '/')])) {
                continue;
            }

            if ($monthYyyymm !== null && $monthYyyymm !== '') {
                $month = preg_replace('/\D+/', '', $monthYyyymm);
                $uploadYmd = $meta['uploaded_at']?->format('Ymd') ?? '';
                if ($uploadYmd === '' || ! str_starts_with($uploadYmd, $month)) {
                    continue;
                }
            }

            $orphans[] = [
                'path' => $rel,
                'field' => $meta['field'],
                'uploaded_at' => $meta['uploaded_at'],
                'empleado_id' => $meta['empleado_id'],
                'uploader_slug' => $meta['uploader_slug'],
            ];
        }

        usort($orphans, fn (array $a, array $b) => strcmp($a['path'], $b['path']));

        return $orphans;
    }

    /**
     * @return array{uploaded_at: ?Carbon, empleado_id: string, uploader_slug: string, field: ?string}
     */
    public function parseFilename(string $basename): array
    {
        $tokens = VentaDocumentUpload::filenameFieldTokens();
        $fieldPattern = implode('|', array_map('preg_quote', $tokens));
        // 20260105_090221_911_adm_carolina_precontractual.pdf
        if (preg_match('/^(\d{8})_(\d{6})_([^_]+)_(.+)_('.$fieldPattern.')\./i', $basename, $m)) {
            $date = $m[1];
            $time = $m[2];
            try {
                $uploaded = Carbon::createFromFormat('YmdHis', $date.$time);
            } catch (Throwable) {
                $uploaded = null;
            }

            return [
                'uploaded_at' => $uploaded,
                'empleado_id' => $m[3],
                'uploader_slug' => $m[4],
                'field' => VentaDocumentUpload::resolveFilenameFieldToSlot(strtolower($m[5])),
            ];
        }

        // Fallback: detectar campo por sufijo en el nombre (tokens largos primero)
        $lower = mb_strtolower($basename);
        foreach ($tokens as $token) {
            if (str_contains($lower, '_'.$token.'.') || str_contains($lower, '_'.$token.'_')) {
                return [
                    'uploaded_at' => null,
                    'empleado_id' => '',
                    'uploader_slug' => '',
                    'field' => VentaDocumentUpload::resolveFilenameFieldToSlot($token),
                ];
            }
        }

        return [
            'uploaded_at' => null,
            'empleado_id' => '',
            'uploader_slug' => '',
            'field' => null,
        ];
    }

    public function normalizeDni(?string $dni): string
    {
        return mb_strtoupper(trim((string) $dni));
    }

    /**
     * Score 0–100. Requiere DNI coincidente; fecha OCR suma; sin fecha OCR solo ventana de carga.
     *
     * @param  array{path: string, field: string, uploaded_at: ?Carbon, empleado_id: string, uploader_slug: string}  $orphan
     * @param  array<string, mixed>  $ocr
     */
    public function scoreMatch(Venta $venta, array $orphan, array $ocr = []): int
    {
        $venta->loadMissing('customer');
        $customerDni = $this->normalizeDni($venta->customer?->dni);
        $ocrDni = $this->normalizeDni($ocr['dni'] ?? null);

        if ($customerDni === '' || $ocrDni === '' || $customerDni !== $ocrDni) {
            return 0;
        }

        $score = 60; // DNI match

        $fechaVenta = $venta->fecha_venta
            ? Carbon::parse($venta->fecha_venta)->startOfDay()
            : null;
        $ocrFecha = filled($ocr['fecha_venta'] ?? null)
            ? Carbon::parse((string) $ocr['fecha_venta'])->startOfDay()
            : null;

        if ($fechaVenta && $ocrFecha) {
            $diff = abs($fechaVenta->diffInDays($ocrFecha));
            if ($diff <= self::FECHA_PROMO_TOLERANCE_DAYS) {
                $score += 35;
            } else {
                return 0; // DNI ok pero Fec.Promo lejos → no auto
            }
        } elseif ($fechaVenta && $orphan['uploaded_at'] instanceof Carbon) {
            $diffUpload = abs($fechaVenta->diffInDays($orphan['uploaded_at']->copy()->startOfDay()));
            if ($diffUpload > self::UPLOAD_WINDOW_DAYS) {
                return 0;
            }
            $score += 10; // solo ventana de carga (más débil)
        } elseif (! $ocrFecha) {
            // Sin fecha OCR y sin uploaded_at usable: no auto-aplicar
            $score += 0;
        }

        $comercialEmpleado = (string) ($venta->comercial?->empleado_id ?? '');
        if ($comercialEmpleado !== '' && $orphan['empleado_id'] !== ''
            && $comercialEmpleado === $orphan['empleado_id']) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Un match es “claro” para auto-apply si score >= 90 (DNI + Fec.Promo)
     * o score >= 70 con un único candidato para ese slot.
     */
    public function isClearAutoMatch(int $score, bool $uniqueForSlot): bool
    {
        if ($score >= 90) {
            return true;
        }

        return $uniqueForSlot && $score >= 70;
    }

    /**
     * @param  list<Venta>  $ventas
     * @param  list<array{path: string, field: string, uploaded_at: ?Carbon, empleado_id: string, uploader_slug: string}>  $orphans
     * @return list<array{
     *     venta_id: int,
     *     nro_contr_adm: ?string,
     *     path: string,
     *     field: string,
     *     score: int,
     *     ocr_dni: string,
     *     ocr_fecha: string,
     *     action: 'auto'|'review'|'skip',
     *     reason: string
     * }>
     */
    public function propose(array $ventas, array $orphans, bool $withOcr = false): array
    {
        $proposals = [];
        $ocrCache = [];

        foreach ($ventas as $venta) {
            $venta->loadMissing(['customer', 'comercial']);
            $emptySlots = $this->emptySlots($venta);
            if ($emptySlots === []) {
                continue;
            }

            $fechaVenta = $venta->fecha_venta
                ? Carbon::parse($venta->fecha_venta)->startOfDay()
                : null;

            $candidatesByField = [];

            foreach ($orphans as $orphan) {
                $field = $orphan['field'];
                if (! in_array($field, $emptySlots, true)) {
                    continue;
                }

                if ($fechaVenta && $orphan['uploaded_at'] instanceof Carbon) {
                    $diffUpload = abs($fechaVenta->diffInDays($orphan['uploaded_at']->copy()->startOfDay()));
                    if ($diffUpload > self::UPLOAD_WINDOW_DAYS) {
                        continue;
                    }
                }

                $ocr = [];
                if ($withOcr) {
                    $path = $orphan['path'];
                    if (! array_key_exists($path, $ocrCache)) {
                        try {
                            $type = $this->extractorTypeForField($field);
                            $ocrCache[$path] = $this->extractWithRetry($type, $path);
                            usleep(400_000);
                        } catch (Throwable $e) {
                            $ocrCache[$path] = ['_error' => $e->getMessage()];
                        }
                    }
                    $ocr = is_array($ocrCache[$path]) ? $ocrCache[$path] : [];
                    if (isset($ocr['_error'])) {
                        $proposals[] = [
                            'venta_id' => $venta->id,
                            'nro_contr_adm' => $venta->nro_contr_adm,
                            'path' => $orphan['path'],
                            'field' => $field,
                            'score' => 0,
                            'ocr_dni' => '',
                            'ocr_fecha' => '',
                            'action' => 'skip',
                            'reason' => 'OCR error: '.$ocr['_error'],
                        ];

                        continue;
                    }
                }

                $score = $withOcr
                    ? $this->scoreMatch($venta, $orphan, $ocr)
                    : 0;

                if (! $withOcr) {
                    // Sin OCR solo listamos candidatos por ventana + slot vacío.
                    $proposals[] = [
                        'venta_id' => $venta->id,
                        'nro_contr_adm' => $venta->nro_contr_adm,
                        'path' => $orphan['path'],
                        'field' => $field,
                        'score' => 0,
                        'ocr_dni' => '',
                        'ocr_fecha' => '',
                        'action' => 'review',
                        'reason' => 'Candidato por ventana de carga (sin OCR). Usa --ocr para auto-match.',
                    ];

                    continue;
                }

                if ($score <= 0) {
                    continue;
                }

                $candidatesByField[$field][] = [
                    'orphan' => $orphan,
                    'ocr' => $ocr,
                    'score' => $score,
                ];
            }

            foreach ($candidatesByField as $field => $candidates) {
                usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
                $unique = count($candidates) === 1;
                $best = $candidates[0];
                $clear = $this->isClearAutoMatch($best['score'], $unique);

                if (! $unique && $best['score'] < 90) {
                    foreach ($candidates as $c) {
                        $proposals[] = [
                            'venta_id' => $venta->id,
                            'nro_contr_adm' => $venta->nro_contr_adm,
                            'path' => $c['orphan']['path'],
                            'field' => $field,
                            'score' => $c['score'],
                            'ocr_dni' => $this->normalizeDni($c['ocr']['dni'] ?? null),
                            'ocr_fecha' => (string) ($c['ocr']['fecha_venta'] ?? ''),
                            'action' => 'review',
                            'reason' => 'Varios candidatos para el slot; revisión manual.',
                        ];
                    }

                    continue;
                }

                $proposals[] = [
                    'venta_id' => $venta->id,
                    'nro_contr_adm' => $venta->nro_contr_adm,
                    'path' => $best['orphan']['path'],
                    'field' => $field,
                    'score' => $best['score'],
                    'ocr_dni' => $this->normalizeDni($best['ocr']['dni'] ?? null),
                    'ocr_fecha' => (string) ($best['ocr']['fecha_venta'] ?? ''),
                    'action' => $clear ? 'auto' : 'review',
                    'reason' => $clear
                        ? 'Match claro (DNI'.($best['score'] >= 90 ? '+Fec.Promo' : '+ventana').').'
                        : 'Match débil; revisar antes de aplicar.',
                ];
            }
        }

        return $proposals;
    }

    /**
     * @param  list<array{venta_id: int, path: string, field: string, action: string}>  $proposals
     * @return array{applied: int, skipped: int}
     */
    public function apply(array $proposals): array
    {
        $applied = 0;
        $skipped = 0;

        foreach ($proposals as $proposal) {
            if (($proposal['action'] ?? '') !== 'auto') {
                $skipped++;

                continue;
            }

            $venta = Venta::query()->find($proposal['venta_id']);
            if (! $venta) {
                $skipped++;

                continue;
            }

            $field = (string) $proposal['field'];
            $path = (string) $proposal['path'];

            if (! in_array($field, self::documentFields(), true)) {
                $skipped++;

                continue;
            }

            if (filled($venta->{$field})) {
                $skipped++;

                continue;
            }

            if (! Storage::disk('public')->exists(preg_replace('#^public/#', '', $path))) {
                $skipped++;

                continue;
            }

            // El path huérfano ya está en public/ventas: enlazar sin copiar.
            $venta->forceFill([$field => ltrim($path, '/')])->saveQuietly();
            $applied++;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * @return list<Venta>
     */
    public function resolveTargetVentas(?int $ventaId, ?string $nro, bool $fromRecovered): array
    {
        if ($ventaId) {
            $venta = Venta::query()->with(['customer', 'comercial'])->find($ventaId);

            return $venta ? [$venta] : [];
        }

        if (filled($nro)) {
            $digits = preg_replace('/\D+/', '', (string) $nro);
            $ventas = Venta::query()
                ->with(['customer', 'comercial'])
                ->where(function ($q) use ($nro, $digits) {
                    $q->where('nro_contr_adm', $nro);
                    if ($digits !== '') {
                        $q->orWhere('nro_contr_adm', $digits)
                            ->orWhere('nro_contr_adm', ltrim($digits, '0'));
                    }
                })
                ->get()
                ->all();

            return $ventas;
        }

        if ($fromRecovered) {
            $ids = ContratoRecoveryItem::query()
                ->where('status', ContratoRecoveryItem::STATUS_ADDED)
                ->whereNotNull('venta_id')
                ->pluck('venta_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $tagged = Venta::query()
                ->where('observaciones_repartidor', 'like', '%'.ContractFromImageRecovery::OBSERVACION_RECUPERADO.'%')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $allIds = array_values(array_unique(array_merge($ids, $tagged)));

            return Venta::query()
                ->with(['customer', 'comercial'])
                ->whereIn('id', $allIds)
                ->get()
                ->all();
        }

        return [];
    }

    /**
     * Resumen ligero (sin OCR) para Excel: candidatos por ventana + slots vacíos.
     *
     * @return array{candidatos: string, auto: string, pendiente_manual: string}
     */
    public function lightweightSummaryForVenta(Venta $venta, ?array $orphans = null): array
    {
        $orphans ??= $this->listOrphans();
        $proposals = $this->propose([$venta], $orphans, withOcr: false);
        $paths = array_values(array_unique(array_column($proposals, 'path')));
        $empty = $this->emptySlots($venta);

        return [
            'candidatos' => $paths === [] ? '—' : implode('; ', array_slice($paths, 0, 8)).(count($paths) > 8 ? '…' : ''),
            'auto' => '— (ejecutar reattach --ocr)',
            'pendiente_manual' => $empty === [] ? 'OK' : implode(', ', $empty),
        ];
    }

    /**
     * @return list<string>
     */
    public function emptySlots(Venta $venta): array
    {
        $empty = [];
        foreach (self::documentFields() as $field) {
            if (blank($venta->{$field})) {
                $empty[] = $field;
            }
        }

        return $empty;
    }

    protected function extractorTypeForField(string $field): string
    {
        return match ($field) {
            'precontractual' => ContractImageExtractor::TYPE_ALBARAN,
            'contrato_firmado' => ContractImageExtractor::TYPE_APP,
            default => ContractImageExtractor::TYPE_OTHER,
        };
    }

    /**
     * @return array<string, true>
     */
    protected function allLinkedDocumentPaths(): array
    {
        $linked = [];
        $fields = self::documentFields();
        $query = Venta::withTrashed()->select(array_merge(['id'], $fields));

        foreach ($query->cursor() as $venta) {
            foreach ($fields as $field) {
                $path = ltrim((string) ($venta->{$field} ?? ''), '/');
                if ($path !== '') {
                    $linked[$path] = true;
                    if (! str_starts_with($path, 'ventas/')) {
                        $linked['ventas/'.basename($path)] = true;
                    }
                }
            }
        }

        return $linked;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractWithRetry(string $type, string $path): array
    {
        if ($this->ocrExtractor !== null) {
            return ($this->ocrExtractor)($type, $path);
        }

        $attempts = 0;
        while (true) {
            try {
                return $this->extractor->extractOne($type, $path);
            } catch (Throwable $e) {
                $attempts++;
                $msg = $e->getMessage();
                $is429 = str_contains($msg, 'HTTP 429') || str_contains($msg, 'Rate limit');
                if (! $is429 || $attempts >= 3) {
                    throw $e;
                }
                sleep(45);
            }
        }
    }
}
