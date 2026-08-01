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

        return array_merge($this->emptyPayload(), Arr::only($decoded, array_keys($this->emptyPayload())));
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

        return match ($type) {
            self::TYPE_APP => "Documento: contrato impreso de la app Ohana. Extrae JSON con claves: {$keys}. nro_contr_adm = Cod. Contrato. fecha_venta = Fec. Promo. fecha_entrega = Fec. Entr. comercial_codes = códigos Com. separados por coma.",
            self::TYPE_ALBARAN => "Documento: albarán / información precontractual manuscrita Ohana. Extrae JSON con claves: {$keys}. dni = NIF. nro_albaran = número del documento. productos_texto = lista de artículos. comercial_codes = códigos de comerciales.",
            default => "Documento relacionado con un contrato Ohana. Extrae JSON con claves: {$keys}. Prioriza DNI, nº contrato, IBAN e importes si aparecen.",
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
