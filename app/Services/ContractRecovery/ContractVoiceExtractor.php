<?php

namespace App\Services\ContractRecovery;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Dictado de voz → transcripción Whisper → JSON de campos de contrato.
 * Misma OPENAI_API_KEY que la visión. Solo SuperAdmin recovery.
 */
final class ContractVoiceExtractor
{
    private ContractImageExtractor $imageExtractor;

    public function __construct(?ContractImageExtractor $imageExtractor = null)
    {
        $this->imageExtractor = $imageExtractor ?? new ContractImageExtractor;
    }

    /**
     * @return array{merged: array<string, mixed>, transcript: string}
     */
    public function extractFromAudioPath(string $relativePath): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Falta OPENAI_API_KEY en el entorno. Puedes rellenar los campos a mano.');
        }

        $absolute = $this->resolveAbsolutePath($relativePath);
        if (! is_file($absolute)) {
            throw new \RuntimeException("No se encuentra el audio: {$relativePath}");
        }

        $transcript = $this->transcribe($absolute, $apiKey);
        $merged = $this->extractFromTranscript($transcript, $apiKey);

        return [
            'merged' => $merged,
            'transcript' => $transcript,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromTranscript(string $transcript, ?string $apiKey = null): array
    {
        $apiKey ??= (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Falta OPENAI_API_KEY en el entorno.');
        }

        $transcript = trim($transcript);
        if ($transcript === '') {
            throw new \RuntimeException('La transcripción está vacía.');
        }

        $keys = implode(', ', array_keys($this->imageExtractor->emptyPayload()));
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
                        'content' => 'Eres un extractor de datos de contratos Ohana a partir de un dictado en español. Devuelves SOLO JSON válido con las claves pedidas. Si un dato no se menciona, usa null. No inventes DNI ni IBAN.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Dictado de recuperación de contrato. Extrae JSON con claves: {$keys}.\n"
                            ."Mapeo: Cod.Contrato / número contrato admin → nro_contr_adm; Fec.Promo / fecha promo → fecha_venta (YYYY-MM-DD); "
                           .'Fec.Entr / fecha entrega → fecha_entrega; Com / comerciales → comercial_codes (códigos separados por coma, ej. 008,004); '
                           ."Rep / repartidor → repartidor_code; NIF/DNI → dni; nº albarán/documento → nro_albaran.\n\n"
                            ."Transcripción:\n{$transcript}",
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI HTTP '.$response->status().': '.$response->body());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '{}');
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('La IA no devolvió JSON válido desde el dictado.');
        }

        $merged = $this->imageExtractor->normalizeExtracted(
            array_merge(
                $this->imageExtractor->emptyPayload(),
                Arr::only($decoded, array_keys($this->imageExtractor->emptyPayload()))
            )
        );
        $merged['_sources'] = ['voice' => 'dictado'];
        $merged['_conflicts'] = [];
        $merged['_transcript'] = $transcript;

        return $merged;
    }

    protected function transcribe(string $absolutePath, string $apiKey): string
    {
        $model = (string) config('services.openai.transcribe_model', 'whisper-1');
        $filename = basename($absolutePath);

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->attach('file', (string) file_get_contents($absolutePath), $filename)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $model,
                'language' => 'es',
                'response_format' => 'json',
            ]);

        if (! $response->successful()) {
            Log::warning('ContractVoiceExtractor transcription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI transcripción HTTP '.$response->status().': '.$response->body());
        }

        $text = trim((string) data_get($response->json(), 'text', ''));
        if ($text === '') {
            throw new \RuntimeException('Whisper no devolvió texto. Prueba a dictar de nuevo o sube otro audio.');
        }

        return $text;
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
