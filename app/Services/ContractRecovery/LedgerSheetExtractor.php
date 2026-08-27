<?php

namespace App\Services\ContractRecovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OCR de hojas manuscritas de listado mensual (nombre / comerciales / importe|estado).
 * Parte la imagen en franjas para no perder líneas densas (gpt-4o-mini suele truncar).
 */
final class LedgerSheetExtractor
{
    /**
     * @return array{lines: list<array<string, mixed>>, raw: array<string, mixed>, error: string|null}
     */
    public function extract(string $absolutePath): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Falta OPENAI_API_KEY en el entorno.');
        }

        if (! is_file($absolutePath)) {
            throw new \RuntimeException("No se encuentra el archivo: {$absolutePath}");
        }

        $tiles = $this->buildTiles($absolutePath);
        $allLines = [];
        $rawParts = [];
        $model = (string) (config('services.openai.ledger_vision_model')
            ?: config('services.openai.vision_model', 'gpt-4o-mini'));

        try {
            foreach ($tiles as $i => $tile) {
                $part = $this->extractTile($apiKey, $model, $tile['data_url'], $i + 1, count($tiles));
                $rawParts[] = $part['raw'];
                foreach ($part['lines'] as $line) {
                    $allLines[] = $line;
                }
                usleep(400_000);
            }
        } finally {
            foreach ($tiles as $tile) {
                if (! empty($tile['temp']) && is_file($tile['temp'])) {
                    @unlink($tile['temp']);
                }
            }
        }

        $merged = $this->dedupeLines($allLines);

        return [
            'lines' => $merged,
            'raw' => ['tiles' => $rawParts, 'line_count' => count($merged)],
            'error' => null,
        ];
    }

    /**
     * @return array{lines: list<array<string, mixed>>, raw: array<string, mixed>}
     */
    protected function extractTile(string $apiKey, string $model, string $dataUrl, int $tileNo, int $tileTotal): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < 5) {
            $attempts++;
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(150)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'temperature' => 0,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Extraes listados manuscritos Ohana. Devuelves SOLO JSON válido. No inventes nombres. Nunca resumas: lista cada línea numerada.',
                            ],
                            [
                                'role' => 'user',
                                'content' => [
                                    ['type' => 'text', 'text' => $this->prompt($tileNo, $tileTotal)],
                                    [
                                        'type' => 'image_url',
                                        'image_url' => [
                                            'url' => $dataUrl,
                                            'detail' => 'high',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);

                if ($response->status() === 429) {
                    $wait = min(30, 2 ** $attempts);
                    Log::warning('Ledger OCR rate limit, retrying', ['wait' => $wait, 'tile' => $tileNo]);
                    sleep($wait);

                    continue;
                }

                if (! $response->successful()) {
                    throw new \RuntimeException('OpenAI HTTP '.$response->status().': '.$response->body());
                }

                $content = (string) data_get($response->json(), 'choices.0.message.content', '');
                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('Respuesta OCR no es JSON válido.');
                }

                $lines = [];
                foreach (($decoded['lines'] ?? []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $nombre = trim((string) ($row['cliente_nombre'] ?? ''));
                    if ($nombre === '' || $this->isPlaceholderName($nombre)) {
                        continue;
                    }
                    $estado = $this->normalizeEstado((string) ($row['estado'] ?? ''), (string) ($row['importe'] ?? ''));
                    $lines[] = [
                        'line_no' => isset($row['line_no']) ? (int) $row['line_no'] : null,
                        'cliente_nombre' => $nombre,
                        'comerciales' => trim((string) ($row['comerciales'] ?? '')),
                        'importe' => $this->normalizeImporte($row['importe'] ?? null),
                        'estado' => $estado,
                        'raw_text' => trim((string) ($row['raw_text'] ?? '')),
                    ];
                }

                return ['lines' => $lines, 'raw' => $decoded];
            } catch (Throwable $e) {
                $lastError = $e;
                if (str_contains($e->getMessage(), '429') && $attempts < 5) {
                    sleep(min(30, 2 ** $attempts));

                    continue;
                }
                throw $e;
            }
        }

        throw $lastError ?? new \RuntimeException('OCR tile falló tras reintentos.');
    }

    protected function prompt(int $tileNo, int $tileTotal): string
    {
        return <<<TXT
Documento: FRACCIÓN {$tileNo}/{$tileTotal} de una hoja manuscrita de listado mensual Ohana (papel cuadriculado).
Puede haber 8–30 líneas en esta fracción. Los números a la izquierda suelen ir en círculo (505, 316, 546…).

Cada línea: número | nombre del cliente | / comerciales | importe € o estado (NULO FINANCIERO, NULO REPARTO, DESISTIMIENTO, NO SALE…).

Devuelve JSON:
{
  "lines": [
    {
      "line_no": 505,
      "cliente_nombre": "Jose Coello Corbacho",
      "comerciales": "Pel - Luna",
      "importe": 379.0 o null,
      "estado": "NULO_FINANCIERO|NULO_REPARTO|DESISTIMIENTO|NULO_AUSENTE|NO_SALE|VENTA|OTRO",
      "raw_text": "transcripción corta de la línea"
    }
  ],
  "lines_found": 12
}

OBLIGATORIO:
- Incluye TODAS las líneas con número + nombre visibles en ESTA fracción. No te quedes en las 3–5 primeras.
- lines_found debe igualar el tamaño del array lines.
- Si hay euros / venta comercial → estado VENTA e importe.
- Si NULO / DESISTIMIENTO / NO SALE → estado correspondiente e importe null.
- No inventes: si no se lee el nombre, omite esa línea.
TXT;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function dedupeLines(array $lines): array
    {
        $seen = [];
        $out = [];
        foreach ($lines as $line) {
            $key = ($line['line_no'] ?? '').'|'.mb_strtoupper((string) ($line['cliente_nombre'] ?? ''));
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $line;
        }

        usort($out, function ($a, $b) {
            $na = $a['line_no'] ?? PHP_INT_MAX;
            $nb = $b['line_no'] ?? PHP_INT_MAX;
            if ($na !== $nb) {
                return $na <=> $nb;
            }

            return strcmp((string) $a['cliente_nombre'], (string) $b['cliente_nombre']);
        });

        return $out;
    }

    protected function isPlaceholderName(string $nombre): bool
    {
        $n = mb_strtolower($nombre);

        return str_contains($n, 'nombre completo')
            || str_contains($n, 'cliente_nombre')
            || $n === 'cliente'
            || preg_match('/^cliente\s*[a-z0-9]?$/u', $n) === 1;
    }

    protected function normalizeEstado(string $estado, string $importeHint): string
    {
        $e = mb_strtoupper(trim($estado));
        $e = str_replace([' ', '-'], '_', $e);

        $map = [
            'NULO_FINANCIERO' => 'NULO_FINANCIERO',
            'NULO_REPARTO' => 'NULO_REPARTO',
            'DESISTIMIENTO' => 'DESISTIMIENTO',
            'NULO_AUSENTE' => 'NULO_AUSENTE',
            'NULO_POR_AUSENTE' => 'NULO_AUSENTE',
            'NO_SALE' => 'NO_SALE',
            'NO_SALE_O_CORRE' => 'NO_SALE',
            'VENTA' => 'VENTA',
            'OTRO' => 'OTRO',
        ];

        if (isset($map[$e])) {
            return $map[$e];
        }

        $blob = mb_strtoupper($estado.' '.$importeHint);
        if (str_contains($blob, 'NULO') && str_contains($blob, 'FINANC')) {
            return 'NULO_FINANCIERO';
        }
        if (str_contains($blob, 'NULO') && (str_contains($blob, 'REPART') || str_contains($blob, 'REPORT'))) {
            return 'NULO_REPARTO';
        }
        if (str_contains($blob, 'DESIST')) {
            return 'DESISTIMIENTO';
        }
        if (str_contains($blob, 'AUSENTE')) {
            return 'NULO_AUSENTE';
        }
        if (str_contains($blob, 'NO SALE') || str_contains($blob, 'NO_SALE')) {
            return 'NO_SALE';
        }
        if (preg_match('/\d/', $importeHint) || $e === '' || $e === 'NULL') {
            return 'VENTA';
        }

        return 'OTRO';
    }

    protected function normalizeImporte(mixed $importe): ?float
    {
        if ($importe === null || $importe === '') {
            return null;
        }
        if (is_numeric($importe)) {
            return round((float) $importe, 2);
        }
        $s = preg_replace('/[^\d.,]/', '', (string) $importe) ?? '';
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }

    /**
     * 3 franjas verticales (con solape) para forzar lectura completa.
     *
     * @return list<array{data_url: string, temp: string|null}>
     */
    protected function buildTiles(string $absolute): array
    {
        if (! function_exists('imagecreatefromjpeg')) {
            [$dataUrl, $temp] = $this->buildDataUrl($absolute);

            return [['data_url' => $dataUrl, 'temp' => $temp]];
        }

        $img = @imagecreatefromjpeg($absolute);
        if ($img === false) {
            [$dataUrl, $temp] = $this->buildDataUrl($absolute);

            return [['data_url' => $dataUrl, 'temp' => $temp]];
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $bands = 3;
        $overlap = (int) round($h * 0.06);
        $bandH = (int) ceil($h / $bands);
        $tiles = [];

        for ($i = 0; $i < $bands; $i++) {
            $y0 = max(0, $i * $bandH - ($i > 0 ? $overlap : 0));
            $y1 = min($h, ($i + 1) * $bandH + ($i < $bands - 1 ? $overlap : 0));
            $ch = $y1 - $y0;
            $crop = imagecrop($img, ['x' => 0, 'y' => $y0, 'width' => $w, 'height' => $ch]);
            if ($crop === false) {
                continue;
            }

            $maxW = 1800;
            if ($w > $maxW) {
                $nw = $maxW;
                $nh = (int) round($ch * ($maxW / $w));
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $crop, 0, 0, 0, 0, $nw, $nh, $w, $ch);
                imagedestroy($crop);
                $crop = $dst;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'ledger_tile_').'.jpg';
            imagejpeg($crop, $tmp, 85);
            imagedestroy($crop);
            $b64 = base64_encode((string) file_get_contents($tmp));
            $tiles[] = [
                'data_url' => 'data:image/jpeg;base64,'.$b64,
                'temp' => $tmp,
            ];
        }

        imagedestroy($img);

        return $tiles !== [] ? $tiles : [['data_url' => $this->buildDataUrl($absolute)[0], 'temp' => null]];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function buildDataUrl(string $absolute): array
    {
        $work = $this->maybeResizeJpeg($absolute);
        $temp = $work !== $absolute ? $work : null;
        $b64 = base64_encode((string) file_get_contents($work));

        return ['data:image/jpeg;base64,'.$b64, $temp];
    }

    protected function maybeResizeJpeg(string $absolute): string
    {
        if (! function_exists('imagecreatefromjpeg')) {
            return $absolute;
        }

        try {
            $img = @imagecreatefromjpeg($absolute);
            if ($img === false) {
                return $absolute;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $maxW = 1800;
            if ($w <= $maxW) {
                imagedestroy($img);

                return $absolute;
            }
            $nw = $maxW;
            $nh = (int) round($h * ($maxW / $w));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $tmp = tempnam(sys_get_temp_dir(), 'ledger_').'.jpg';
            imagejpeg($dst, $tmp, 82);
            imagedestroy($img);
            imagedestroy($dst);

            return $tmp;
        } catch (Throwable $e) {
            Log::warning('LedgerSheetExtractor resize failed', ['error' => $e->getMessage()]);

            return $absolute;
        }
    }
}
