<?php

namespace App\Services\ContractRecovery;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extrae campos de hasta 3 documentos (contrato app / albarán / otro) vía visión OpenAI.
 * Aislado del flujo comercial: solo usado desde SuperAdmin recovery.
 */
final class ContractImageExtractor
{
    public const TYPE_APP = 'app_contract';

    public const TYPE_ALBARAN = 'albaran';

    public const TYPE_OTHER = 'other';

    /**
     * @param  list<array{type: string, path: string, label?: string|null}>  $documents
     * @return array{merged: array<string, mixed>, per_document: list<array<string, mixed>>, conflicts: list<string>, errors: list<string>}
     */
    public function extractAndMerge(array $documents): array
    {
        $perDocument = [];
        $errors = [];

        foreach (array_slice($documents, 0, 3) as $doc) {
            $type = (string) ($doc['type'] ?? self::TYPE_OTHER);
            $path = (string) ($doc['path'] ?? '');

            try {
                $perDocument[] = [
                    'type' => $type,
                    'path' => $path,
                    'label' => $doc['label'] ?? null,
                    'data' => $this->extractOne($type, $path),
                ];
            } catch (Throwable $e) {
                Log::warning('ContractImageExtractor failed', [
                    'type' => $type,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "{$type}: ".$e->getMessage();
                $perDocument[] = [
                    'type' => $type,
                    'path' => $path,
                    'label' => $doc['label'] ?? null,
                    'data' => $this->emptyPayload(),
                ];
            }
        }

        [$merged, $conflicts] = $this->merge($perDocument);

        return [
            'merged' => $merged,
            'per_document' => $perDocument,
            'conflicts' => $conflicts,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractOne(string $type, string $relativePath): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Falta OPENAI_API_KEY en el entorno. Puedes rellenar los campos a mano.');
        }

        $absolute = $this->resolveAbsolutePath($relativePath);
        if (! is_file($absolute)) {
            throw new \RuntimeException("No se encuentra el archivo: {$relativePath}");
        }

        $mime = mime_content_type($absolute) ?: 'image/jpeg';
        $b64 = base64_encode((string) file_get_contents($absolute));
        $dataUrl = "data:{$mime};base64,{$b64}";

        $prompt = $this->promptFor($type);

        $model = (string) config('services.openai.vision_model', 'gpt-4o-mini');

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un extractor de datos de contratos Ohana. Devuelves SOLO JSON válido con las claves pedidas. Si un dato no se lee, usa null. No inventes DNI ni IBAN.',
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI HTTP '.$response->status().': '.$response->body());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '{}');
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('La IA no devolvió JSON válido.');
        }

        return $this->normalizeExtracted(
            array_merge($this->emptyPayload(), Arr::only($decoded, array_keys($this->emptyPayload())))
        );
    }

    /**
     * Normaliza fechas europeas, nº contrato y códigos Com. del encabezado del contrato.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeExtracted(array $data): array
    {
        $data['nro_contr_adm'] = $this->normalizeNroContrato($data['nro_contr_adm'] ?? null);
        $data['fecha_venta'] = $this->normalizeDate($data['fecha_venta'] ?? null);
        $data['fecha_entrega'] = $this->normalizeDate($data['fecha_entrega'] ?? null);
        $data['comercial_codes'] = $this->normalizeComercialCodes($data['comercial_codes'] ?? null);
        $data['repartidor_code'] = $this->normalizeEmpleadoCode($data['repartidor_code'] ?? null);
        $data['horario_entrega'] = $this->normalizeHorario($data['horario_entrega'] ?? null);

        return $data;
    }

    public function normalizeNroContrato(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? ltrim($digits, '0') ?: '0' : null;
    }

    public function normalizeDate(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $raw = trim((string) $value);
        foreach (['d-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y', 'Y-m-d'] as $fmt) {
            try {
                $dt = \Illuminate\Support\Carbon::createFromFormat($fmt, $raw);

                return $dt->format('Y-m-d');
            } catch (Throwable) {
            }
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    public function normalizeComercialCodes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = is_array($value)
            ? $value
            : (preg_split('/[^0-9A-Za-z]+/', (string) $value) ?: []);

        $codes = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || ! preg_match('/^\d+$/', $part)) {
                continue;
            }
            $codes[] = str_pad(ltrim($part, '0') ?: '0', 3, '0', STR_PAD_LEFT);
        }

        $codes = array_values(array_unique($codes));

        return $codes === [] ? null : implode(',', $codes);
    }

    /** Un solo código de empleado (ej. Rep. 005 → 005). */
    public function normalizeEmpleadoCode(mixed $value): ?string
    {
        $codes = $this->normalizeComercialCodes($value);
        if ($codes === null) {
            return null;
        }

        return explode(',', $codes)[0] ?? null;
    }

    protected function normalizeHorario(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return mb_strtoupper(trim((string) $value));
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $perDocument
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public function merge(array $perDocument): array
    {
        $priority = [self::TYPE_APP => 1, self::TYPE_ALBARAN => 2, self::TYPE_OTHER => 3];
        usort($perDocument, function ($a, $b) use ($priority) {
            return ($priority[$a['type']] ?? 99) <=> ($priority[$b['type']] ?? 99);
        });

        $merged = $this->emptyPayload();
        $conflicts = [];
        $sources = [];

        foreach ($perDocument as $doc) {
            $data = $doc['data'] ?? [];
            foreach ($this->emptyPayload() as $key => $_) {
                $incoming = $data[$key] ?? null;
                if ($incoming === null || $incoming === '') {
                    continue;
                }
                $current = $merged[$key] ?? null;
                if ($current === null || $current === '') {
                    $merged[$key] = $incoming;
                    $sources[$key] = $doc['type'];
                    continue;
                }
                if ((string) $current !== (string) (is_array($incoming) ? json_encode($incoming) : $incoming)
                    && (string) json_encode($current) !== (string) json_encode($incoming)) {
                    $conflicts[] = $key.' (prioridad '.$sources[$key].' vs '.$doc['type'].')';
                }
            }
        }

        $merged = $this->normalizeExtracted($merged);
        $merged['_sources'] = $sources;
        $merged['_conflicts'] = $conflicts;

        return [$merged, $conflicts];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(): array
    {
        return [
            'dni' => null,
            'nro_contr_adm' => null,
            'nro_albaran' => null,
            'cliente_nombre' => null,
            'fecha_venta' => null,
            'fecha_entrega' => null,
            'horario_entrega' => null,
            'comercial_codes' => null,
            'repartidor_code' => null,
            'importe_total' => null,
            'entrada' => null,
            'cuota_mensual' => null,
            'num_cuotas' => null,
            'iban' => null,
            'productos_texto' => null,
            'direccion' => null,
            'telefonos' => null,
            'observaciones' => null,
        ];
    }

    protected function promptFor(string $type): string
    {
        $keys = implode(', ', array_keys($this->emptyPayload()));

        $headerMap = <<<'TXT'
Encabezado típico del CONTRATO Ohana (mapeo OBLIGATORIO):
- "Cod.Contrato" / "Cod. Contrato" → nro_contr_adm (número del contrato admin; ej. 1189). Solo el número.
- "Fec.Promo." / "Fec. Promo." → fecha_venta (fecha del contrato admin / promo). Formato YYYY-MM-DD. Ej. 02-10-2025 → 2025-10-02.
- "Fec.Entr." / "Fec. Entr." → fecha_entrega. Formato YYYY-MM-DD. Ej. 03-10-2025 → 2025-10-03.
- "Com." / "Com:" → comercial_codes: los 1 o 2 códigos de comercial del contrato, separados por coma.
  Ej. "008 - 004" → "008,004". No confundir con "Rep." (repartidor).
- "Rep." / "Rep:" → repartidor_code: id de empleado del repartidor del contrato (ej. 005). Un solo código. No es comercial.
- "Hora Entr." → horario_entrega (ej. TD, TM).
- "Cód.Cliente" NO es nro_contr_adm.
TXT;

        return match ($type) {
            self::TYPE_APP => "Documento: contrato impreso Ohana (app). Extrae JSON con claves: {$keys}.\n{$headerMap}",
            self::TYPE_ALBARAN => "Documento: albarán / información precontractual manuscrita Ohana. Extrae JSON con claves: {$keys}. dni = NIF. nro_albaran = número del documento. productos_texto = lista de artículos. Si aparece Com./códigos comercial → comercial_codes. Si aparece Rep. → repartidor_code. Si hay Fec.Promo./Fec.Entr./Cod.Contrato usa el mismo mapeo del contrato.",
            default => "Documento relacionado con un contrato Ohana. Extrae JSON con claves: {$keys}.\n{$headerMap}\nPrioriza DNI, Cod.Contrato, Fec.Promo., Fec.Entr., Com., Rep., IBAN e importes.",
        };
    }

    protected function resolveAbsolutePath(string $relativePath): string
    {
        if (str_starts_with($relativePath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $relativePath)) {
            return $relativePath;
        }

        foreach (['local', 'public'] as $disk) {
            $full = Storage::disk($disk)->path($relativePath);
            if (is_file($full)) {
                return $full;
            }
        }

        return storage_path('app/'.$relativePath);
    }
}
