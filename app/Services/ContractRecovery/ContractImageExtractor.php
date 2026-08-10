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

    /** DNI / NIE español (anverso o reverso físico). */
    public const TYPE_DNI_CARD = 'dni_card';

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

        [$dataUrl, $tempImage] = $this->buildVisionDataUrl($absolute);

        try {
            $prompt = $this->promptFor($type);

            // gpt-4o-mini clasifica bien pero lee mal letra pequeña (DNI, IBAN...);
            // usamos el modelo completo para la extracción de datos en sí.
            $model = (string) config('services.openai.extraction_model', 'gpt-4o');

            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un extractor de datos de contratos Ohana. Devuelves SOLO JSON válido con las claves pedidas. Si un dato no se lee, usa null. No inventes DNI ni IBAN. El DNI debe leerse ÚNICAMENTE de lo visible en la imagen (foto o MRZ); nunca inventes ni completes un DNI a partir de suposiciones.',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']],
                            ],
                        ],
                    ],
                ]);
        } finally {
            if ($tempImage !== null && is_file($tempImage)) {
                @unlink($tempImage);
            }
        }

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
        $data['dni'] = $this->normalizeSpanishId($data['dni'] ?? null)
            ?? $this->normalizeSpanishId($data['mrz_raw'] ?? null)
            ?? $this->normalizeSpanishId($data['observaciones'] ?? null);
        // El DNI/NIE español tiene letra de control determinista (dígitos mod 23).
        // Si no cuadra, es un dato erróneo (OCR o alucinación del modelo): mejor
        // dejarlo en blanco para revisión manual que guardar un DNI falso.
        if ($data['dni'] !== null && ! $this->isValidSpanishId($data['dni'])) {
            $data['dni'] = null;
        }
        // "12345678Z"/"12345678A" es el DNI de ejemplo típico de tutoriales (y de
        // hecho la Z sí cuadra con el checksum, por eso lo eligen). Si el modelo
        // lo repite igual en varios contratos de personas distintas es alucinación,
        // no un dato real: lo saneamos igualmente.
        if ($data['dni'] !== null && str_starts_with($data['dni'], '12345678')) {
            $data['dni'] = null;
        }
        $data['documento_tipo'] = $this->normalizeDocumentoTipo($data['documento_tipo'] ?? null);
        $data['codigo_postal'] = $this->normalizePostalCode($data['codigo_postal'] ?? null)
            ?? $this->normalizePostalCode($data['direccion'] ?? null);
        // "C. IMPORTE Y FORMA DE PAGO": el modelo suele copiar el número tal cual está impreso
        // (formato español, ej. "2.628,60 €"). El formulario usa inputs numéricos (punto decimal,
        // sin símbolo de moneda), así que lo normalizamos aquí para que se vea el dato en vez de
        // quedar vacío por no ser un número JS/HTML válido.
        $data['importe_total'] = $this->normalizeMoney($data['importe_total'] ?? null);
        $data['entrada'] = $this->normalizeMoney($data['entrada'] ?? null);
        $data['cuota_mensual'] = $this->normalizeMoney($data['cuota_mensual'] ?? null);
        $data['num_cuotas'] = $this->normalizeNumCuotas($data['num_cuotas'] ?? null);
        // El modelo suele copiar el "Domicilio:" tal cual, con el CP pegado dentro
        // (ej. "Avda. Castelao 67 9ºB 36209"). Una vez extraído el CP por separado,
        // lo quitamos de la dirección para que quede limpia (solo calle/piso), que
        // es el formato con el que se guarda al cliente en la app (primary_address
        // y postal_code son columnas separadas).
        $data['direccion'] = $this->stripPostalCodeFromDireccion($data['direccion'] ?? null, $data['codigo_postal']);

        return $data;
    }

    /**
     * Código postal español (5 dígitos). Acepta el valor directo o lo detecta
     * dentro de un texto libre (ej. dentro de "direccion" si Vision no lo separó).
     */
    public function normalizePostalCode(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $raw = (string) $value;
        if (preg_match('/\b(\d{5})\b/', $raw, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Quita el código postal (si aparece) de la línea de dirección, dejando solo
     * calle/número/piso. No toca nada si no hay CP o la dirección viene vacía.
     */
    public function stripPostalCodeFromDireccion(mixed $direccion, ?string $codigoPostal): ?string
    {
        if (! filled($direccion)) {
            return null;
        }

        $raw = trim((string) $direccion);
        if ($codigoPostal !== null) {
            $raw = preg_replace('/\b'.preg_quote($codigoPostal, '/').'\b/', '', $raw) ?? $raw;
        }

        // Limpia comas/guiones sueltos y espacios dobles que deja el hueco del CP.
        $raw = preg_replace('/\s{2,}/', ' ', $raw) ?? $raw;
        $raw = preg_replace('/\s*,\s*,/', ',', $raw) ?? $raw;
        $raw = trim($raw, " \t\n\r\0\x0B,-");

        return $raw !== '' ? $raw : null;
    }

    /**
     * Importe en euros de la tabla "C. IMPORTE Y FORMA DE PAGO" (entrada, cuota_mensual,
     * importe_total). Acepta formato español tal cual viene impreso ("2.628,60 €", "87,62€",
     * "0,00") y también JSON numérico plano (2628.6). Devuelve siempre con punto decimal y
     * sin símbolo, listo para un input numérico (ej. "2628.60"). Null si no es un importe válido.
     */
    public function normalizeMoney(mixed $value): ?string
    {
        if (! filled($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        $raw = trim(preg_replace('/[€\s]/u', '', (string) $value) ?? '');
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d{1,2}$/', $raw)) {
            // Miles con punto + decimales con coma: "2.628,60" → "2628.60"
            $raw = str_replace(['.', ','], ['', '.'], $raw);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+\.\d{1,2}$/', $raw)) {
            // Formato EEUU (a veces lo devuelve así el modelo): "3,564.00" → "3564.00"
            $raw = str_replace(',', '', $raw);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $raw)) {
            // Solo miles con punto, sin decimales: "1.234" → "1234"
            $raw = str_replace('.', '', $raw);
        } elseif (preg_match('/^-?\d+,\d{1,2}$/', $raw)) {
            // Solo coma decimal: "87,62" → "87.62"
            $raw = str_replace(',', '.', $raw);
        } elseif (! preg_match('/^-?\d+(\.\d{1,2})?$/', $raw)) {
            // No es un importe reconocible: mejor null que un dato inventado.
            return null;
        }

        return is_numeric($raw) ? number_format((float) $raw, 2, '.', '') : null;
    }

    /**
     * Nº de cuotas de la misma tabla "C. IMPORTE Y FORMA DE PAGO" (columna "Nº DE CUOTAS").
     */
    public function normalizeNumCuotas(mixed $value): ?string
    {
        if (! filled($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        if (preg_match('/(\d{1,3})/', (string) $value, $m)) {
            return (string) (int) $m[1];
        }

        return null;
    }

    /**
     * Extrae DNI (8 dígitos + letra) o NIE (X/Y/Z + 7 dígitos + letra) de un texto,
     * incluida la zona MRZ (IDESP…36026170M…).
     */
    public function normalizeSpanishId(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $raw = mb_strtoupper(trim((string) $value));
        $raw = str_replace(["\n", "\r", '<', ' ', '-'], '', $raw);
        $raw = preg_replace('/^DNI/', '', $raw) ?? $raw;

        // MRZ línea 1 típica: IDESPBCJ151164436026170M<<<<<<
        if (preg_match('/IDESP[A-Z0-9]*?(\d{8}[A-Z])/', $raw, $m)) {
            return $m[1];
        }

        // 36026170M (con o sin prefijo DNI ya quitado)
        if (preg_match('/(\d{8}[A-Z])/', $raw, $m)) {
            return $m[1];
        }

        // NIE
        if (preg_match('/([XYZ]\d{7}[A-Z])/', $raw, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Valida el dígito/letra de control del DNI (8 dígitos + letra) o NIE
     * (X/Y/Z + 7 dígitos + letra) con el algoritmo oficial: número mod 23
     * indexa la tabla "TRWAGMYFPDXBNJZSQVHLCKE". Sirve para detectar
     * alucinaciones o errores de OCR: un DNI real siempre cuadra.
     */
    public function isValidSpanishId(string $value): bool
    {
        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $value = mb_strtoupper(trim($value));

        if (preg_match('/^(\d{8})([A-Z])$/', $value, $m)) {
            $number = (int) $m[1];
            return $letters[$number % 23] === $m[2];
        }

        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $value, $m)) {
            $prefix = ['X' => '0', 'Y' => '1', 'Z' => '2'][$m[1]];
            $number = (int) ($prefix . $m[2]);
            return $letters[$number % 23] === $m[3];
        }

        return false;
    }

    public function normalizeDocumentoTipo(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $v = mb_strtolower(trim((string) $value));

        return match (true) {
            str_contains($v, 'anverso') || $v === 'dni_anverso' || $v === 'front' => 'dni_anverso',
            str_contains($v, 'reverso') || $v === 'dni_reverso' || $v === 'back' || str_contains($v, 'mrz') => 'dni_reverso',
            str_contains($v, 'precontract') || str_contains($v, 'albaran') => 'precontractual',
            str_contains($v, 'contrato') => 'contrato_firmado',
            str_contains($v, 'titular') => 'documento_titularidad',
            default => null,
        };
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
            'documento_tipo' => null,
            'mrz_raw' => null,
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
            'codigo_postal' => null,
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
- "Domicilio" → direccion: copia la línea completa de "Domicilio:" tal cual aparece (calle,
  número, piso/puerta). Es habitual que el código postal (5 dígitos) esté pegado al final de esa
  misma línea, con o sin ciudad detrás (ej. "Avda. Castelao 67 9ºB 36209" o "36213 Vigo
  (Pontevedra)"): en ambos casos extrae esos 5 dígitos también por separado en codigo_postal
  (ej. "36209" / "36213"). Si no hay código postal visible, usa null.
- IMPORTANTE — nro_contr_adm SOLO si está ETIQUETADO como "Cod.Contrato" / "Cod. Contrato".
  Muchos albaranes/notas de entrega llevan un número de serie propio impreso arriba (del propio
  talonario), SIN esa etiqueta: eso NO es el número de contrato, es solo el correlativo del papel.
  Si ves un número grande o destacado pero SIN la palabra "Cod.Contrato" al lado, deja nro_contr_adm
  en null — mejor vacío para revisión manual que un número de contrato falso.
- DNI/NIE (MUY IMPORTANTE, no te lo saltes): en la sección "A. DATOS PERSONALES DEL CLIENTE" hay una
  línea con la etiqueta "DNI/NIE" (o "NIF/NIE"), justo debajo de "Nombre y apellidos" y encima de
  "Fecha nacimiento". Contiene el documento del cliente: 8 dígitos + 1 letra final (ej. 36008542H) o
  NIE (letra X/Y/Z + 7 dígitos + letra). Lee esa línea dígito a dígito, con mucho cuidado de no
  transponer cifras ni confundirla con "Cód.Cliente" (que es un número aparte, más corto, en el
  encabezado) ni con los teléfonos de la línea de abajo. Si la línea existe pero un dígito no se ve
  con total claridad, aun así devuelve tu mejor lectura completa (no la dejes en null solo por duda:
  para eso existe la validación posterior).
- "C. IMPORTE Y FORMA DE PAGO" (MUY IMPORTANTE, no te lo saltes): es una tabla con 5 columnas, justo
  debajo de "B. RELACIÓN DE ARTÍCULOS". Lee cada celda de esa fila y mapea exactamente así (no
  confundas estas cifras con ninguna otra del documento):
  - Columna "ENTRADA" → entrada (importe en euros, ej. "0,00 €" → 0.00).
  - Columna "Nº DE CUOTAS" → num_cuotas (número entero de cuotas, ej. 30).
  - Columna "CUOTA MENSUAL" → cuota_mensual (importe en euros, ej. "87,62 €" → 87.62).
  - Columna "IMPORTE TOTAL" (la última, más a la derecha) → importe_total (importe en euros, ej.
    "2.628,60 €" → 2628.60).
  Copia el número tal cual está impreso en cada celda (con su coma decimal si la lleva); no
  redondees, no inventes ni mezcles columnas. Si una celda está vacía o no se lee con claridad, usa
  null solo en esa clave.
TXT;

        $dniCard = <<<'TXT'
Documento: DNI o NIE español (tarjeta física), anverso O reverso.
Devuelve JSON con claves: {$keys}.

REGLAS DNI (OBLIGATORIO):
- dni: el número de documento español. Formato típico 8 dígitos + letra (ej. 36026170M) o NIE (X1234567L).
  Busca: etiqueta "DNI" en anverso (abajo), o en la zona MRZ del reverso (líneas IDESP… / SANTOS<…).
  En MRZ el DNI va embebido, ej. IDESPBCJ151164436026170M<<<<<< → dni=36026170M.
- mrz_raw: copia literal de las 3 líneas MRZ si aparecen (reverso). Si no hay MRZ, null.
- documento_tipo:
  - "dni_anverso" si se ve foto del titular, apellidos/nombre grandes, "DOCUMENTO NACIONAL DE IDENTIDAD".
  - "dni_reverso" si se ve domicilio, chip, o zona MRZ (IDESP… / fechas / apellido<<nombre).
- cliente_nombre: nombre completo si se lee.
No inventes el DNI: solo si lo ves claramente en foto o MRZ.
TXT;

        return match ($type) {
            self::TYPE_APP => "Documento: contrato impreso Ohana (app). Extrae JSON con claves: {$keys}.\n{$headerMap}",
            self::TYPE_ALBARAN => "Documento: albarán / información precontractual manuscrita Ohana. Extrae JSON con claves: {$keys}. dni = NIF. nro_albaran = número del documento (el correlativo propio del talonario, impreso arriba SIN etiqueta). productos_texto = lista de artículos. Si aparece Com./códigos comercial → comercial_codes. Si aparece Rep. → repartidor_code. Si hay Fec.Promo./Fec.Entr./Cod.Contrato usa el mismo mapeo del contrato. nro_contr_adm SOLO si ves literalmente la etiqueta \"Cod.Contrato\"; el número de serie del propio albarán (arriba, sin etiquetar) va en nro_albaran, NUNCA en nro_contr_adm.",
            self::TYPE_DNI_CARD => str_replace('{$keys}', $keys, $dniCard),
            default => "Documento relacionado con un contrato Ohana (puede ser DNI, precontractual/albarán, contrato, titularidad…). Extrae JSON con claves: {$keys}.\n{$headerMap}\nSi es DNI/NIE: rellena dni (8 dígitos+letra o NIE), documento_tipo (dni_anverso|dni_reverso) y mrz_raw si hay MRZ. Prioriza DNI, Cod.Contrato, Fec.Promo., Fec.Entr., Com., Rep., IBAN e importes.\nCLASIFICA SIEMPRE documento_tipo (obligatorio, aparte de DNI):\n- \"precontractual\": hoja AMARILLA titulada \"INFORMACIÓN PRECONTRACTUAL\"/\"DATOS PERSONALES DEL CLIENTE\" a mano, con un \"DOCUMENTO: NNNNNN\" impreso arriba (ese número NO es nro_contr_adm, es el correlativo del talonario). Es el albarán.\n- \"contrato\": hoja BLANCA impresa titulada \"CONTRATO\", con \"Cod.Contrato: NNNN\" impreso arriba (ese SÍ es nro_contr_adm).\n- \"documento_titularidad\": recibo/factura de luz, agua, etc.\n- \"otro\": cualquier otro caso (nómina, pensión...).",
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

    /**
     * Vision solo acepta imágenes. PDF → primera página JPG (Imagick / pdftoppm / gs).
     *
     * @return array{0: string, 1: string|null} [dataUrl, tempPathToCleanup]
     */
    protected function buildVisionDataUrl(string $absolute): array
    {
        $mime = mime_content_type($absolute) ?: '';
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        if ($this->isSupportedImageMime($mime, $ext)) {
            $corrected = $this->exifCorrectedCopy($absolute, $mime);
            if ($corrected !== null) {
                $b64 = base64_encode((string) file_get_contents($corrected));

                return ['data:image/jpeg;base64,'.$b64, $corrected];
            }

            $mime = $mime !== '' && str_starts_with($mime, 'image/') ? $mime : $this->mimeFromExtension($ext);
            $b64 = base64_encode((string) file_get_contents($absolute));

            return ["data:{$mime};base64,{$b64}", null];
        }

        if ($this->isPdf($mime, $ext)) {
            $jpg = $this->convertPdfFirstPageToJpeg($absolute);
            $b64 = base64_encode((string) file_get_contents($jpg));

            return ['data:image/jpeg;base64,'.$b64, $jpg];
        }

        throw new \RuntimeException(
            "Tipo no soportado para OCR ({$mime}/.{$ext}). Solo imágenes o PDF."
        );
    }

    /**
     * Las fotos de móvil suelen guardar la rotación como flag EXIF Orientation
     * en vez de rotar los píxeles (p.ej. Orientation=6 = "girar 90° al
     * mostrar"). La API de Vision de OpenAI decodifica el buffer de píxeles
     * tal cual y NO aplica ese flag, así que sin este paso, cualquier foto con
     * Orientation distinto de 1 llega girada al modelo (aunque en el Finder o
     * la app Fotos se vea perfectamente derecha), causando errores al leer el
     * nº de contrato y el DNI. Devuelve la ruta de una copia ya corregida y
     * sin flag EXIF pendiente, o null si no hacía falta corregir nada.
     */
    public function exifCorrectedCopy(string $absolutePath, string $mime): ?string
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data') || ! extension_loaded('gd')) {
            return null;
        }

        $exif = @exif_read_data($absolutePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        if (! in_array($orientation, [3, 6, 8], true)) {
            return null;
        }

        $src = @imagecreatefromjpeg($absolutePath);
        if (! $src) {
            return null;
        }

        // Grados en sentido horario que corrigen cada valor EXIF estándar.
        $degrees = match ($orientation) {
            3 => 180,
            6 => 90,
            8 => 270,
            default => 0,
        };

        // imagerotate() gira en sentido antihorario: invertimos el ángulo para
        // que $degrees siga significando "corrección en sentido horario".
        $rotated = imagerotate($src, -$degrees, 0);
        imagedestroy($src);

        if (! $rotated) {
            return null;
        }

        $out = sys_get_temp_dir().'/ohana_exif_'.uniqid('', true).'.jpg';
        imagejpeg($rotated, $out, 92);
        imagedestroy($rotated);

        return is_file($out) ? $out : null;
    }

    protected function isSupportedImageMime(string $mime, string $ext): bool
    {
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return true;
        }

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            && ($mime === '' || str_starts_with($mime, 'image/') || $mime === 'application/octet-stream');
    }

    protected function isPdf(string $mime, string $ext): bool
    {
        return $mime === 'application/pdf' || $ext === 'pdf';
    }

    protected function mimeFromExtension(string $ext): string
    {
        return match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Primera página del PDF a JPEG temporal (~200 DPI).
     */
    protected function convertPdfFirstPageToJpeg(string $pdfPath): string
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            return $this->convertPdfWithImagick($pdfPath);
        }

        $pdftoppm = $this->findBinary('pdftoppm');
        if ($pdftoppm !== null) {
            return $this->convertPdfWithPdftoppm($pdfPath, $pdftoppm);
        }

        $gs = $this->findBinary('gs');
        if ($gs !== null) {
            return $this->convertPdfWithGhostscript($pdfPath, $gs);
        }

        throw new \RuntimeException(
            'PDF detectado pero falta conversor (instala poppler-utils: pdftoppm, o Imagick/Ghostscript).'
        );
    }

    protected function convertPdfWithImagick(string $pdfPath): string
    {
        $out = $this->tempJpegPath();
        $im = new \Imagick;
        try {
            $im->setResolution(200, 200);
            $im->readImage($pdfPath.'[0]');
            $im->setImageBackgroundColor('white');
            $flattened = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $flattened->setImageFormat('jpeg');
            $flattened->setImageCompressionQuality(85);
            if (! $flattened->writeImage($out)) {
                throw new \RuntimeException('Imagick no pudo escribir el JPEG.');
            }
            $flattened->clear();
            $flattened->destroy();
        } finally {
            $im->clear();
            $im->destroy();
        }

        if (! is_file($out) || filesize($out) === 0) {
            @unlink($out);
            throw new \RuntimeException('Imagick generó un JPEG vacío.');
        }

        return $out;
    }

    protected function convertPdfWithPdftoppm(string $pdfPath, string $binary): string
    {
        $prefix = sys_get_temp_dir().'/ohana_pdf_'.uniqid('', true);
        $cmd = sprintf(
            '%s -jpeg -r 200 -singlefile -f 1 -l 1 %s %s 2>&1',
            escapeshellarg($binary),
            escapeshellarg($pdfPath),
            escapeshellarg($prefix)
        );
        exec($cmd, $output, $code);
        $out = $prefix.'.jpg';
        if ($code !== 0 || ! is_file($out) || filesize($out) === 0) {
            @unlink($out);
            throw new \RuntimeException('pdftoppm falló: '.mb_strimwidth(implode(' ', $output), 0, 120, '…'));
        }

        return $out;
    }

    protected function convertPdfWithGhostscript(string $pdfPath, string $binary): string
    {
        $out = $this->tempJpegPath();
        $cmd = sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -dFirstPage=1 -dLastPage=1 -sDEVICE=jpeg -r200 -dJPEGQ=85 -sOutputFile=%s %s 2>&1',
            escapeshellarg($binary),
            escapeshellarg($out),
            escapeshellarg($pdfPath)
        );
        exec($cmd, $output, $code);
        if ($code !== 0 || ! is_file($out) || filesize($out) === 0) {
            @unlink($out);
            throw new \RuntimeException('Ghostscript falló: '.mb_strimwidth(implode(' ', $output), 0, 120, '…'));
        }

        return $out;
    }

    protected function tempJpegPath(): string
    {
        return sys_get_temp_dir().'/ohana_pdf_'.uniqid('', true).'.jpg';
    }

    protected function findBinary(string $name): ?string
    {
        $cmd = 'command -v '.escapeshellarg($name).' 2>/dev/null';
        $path = trim((string) shell_exec($cmd));

        return $path !== '' && is_executable($path) ? $path : null;
    }
}
