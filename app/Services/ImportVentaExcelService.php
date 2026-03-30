<?php

namespace App\Services;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Enums\OrigenVenta;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportVentaExcelService
{
    public function procesarFila(
        array $row,
        OrigenVenta $origenVenta = OrigenVenta::VENTA_NORMAL,
        ?string $archivoImportado = null
    ): void {
        Log::debug('ImportVentaExcelService: iniciando procesamiento de fila', [
            'archivo_importado' => $archivoImportado,
            'row' => $row,
            'origen_venta' => $origenVenta->value,
            'auth_user_id' => auth()->id(),
        ]);

        $fechaVenta = $this->parseFechaExcel($row[0] ?? null);

        Log::debug('ImportVentaExcelService: fecha procesada', [
            'valor_original' => $row[0] ?? null,
            'fecha_parseada' => $fechaVenta?->toDateTimeString(),
        ]);

        if (!$fechaVenta) {
            Log::warning('ImportVentaExcelService: fila descartada por fecha inválida', [
                'archivo_importado' => $archivoImportado,
                'valor_fecha' => $row[0] ?? null,
                'row' => $row,
            ]);

            return;
        }

        $telefonoRaw = $row[5] ?? null;
        $telefonos = $this->extraerTelefonos($telefonoRaw);

        Log::debug('ImportVentaExcelService: teléfonos extraídos', [
            'telefono_raw' => $telefonoRaw,
            'telefonos' => $telefonos,
        ]);

        if (empty($telefonos)) {
            Log::warning('ImportVentaExcelService: fila descartada por no tener teléfonos válidos', [
                'archivo_importado' => $archivoImportado,
                'telefono_raw' => $telefonoRaw,
                'row' => $row,
            ]);

            return;
        }

        try {
            DB::transaction(function () use ($row, $fechaVenta, $telefonos, $origenVenta, $archivoImportado) {

                $nombre = trim((string) ($row[3] ?? ''));
                $apellidos = trim((string) ($row[4] ?? ''));
                $dni = trim((string) ($row[8] ?? ''));
                $importe = $this->parseImporte($row[9] ?? 0);

                $direccion = $this->nullIfBlank($row[6] ?? null);
                $provincia = $this->nullIfBlank($row[7] ?? null);

                $seguimiento = $this->nullIfBlank($row[12] ?? null);
                $financieras = $this->nullIfBlank($row[13] ?? null);
                $pasadas = $this->nullIfBlank($row[14] ?? null);

                $comercialNombre = trim((string) ($row[15] ?? ''));

                Log::debug('ImportVentaExcelService: datos principales normalizados', [
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'dni' => $dni,
                    'importe' => $importe,
                    'direccion' => $direccion,
                    'provincia' => $provincia,
                    'seguimiento' => $seguimiento,
                    'financieras' => $financieras,
                    'pasadas' => $pasadas,
                    'comercial_nombre' => $comercialNombre,
                ]);

                /*
                |--------------------------------------------------------------------------
                | 1) Buscar cliente
                |--------------------------------------------------------------------------
                */
                $customer = null;

                if ($dni !== '') {
                    Log::debug('ImportVentaExcelService: buscando cliente por DNI', [
                        'dni' => $dni,
                    ]);

                    $customer = Customer::where('dni', $dni)->first();

                    Log::debug('ImportVentaExcelService: resultado búsqueda por DNI', [
                        'dni' => $dni,
                        'customer_id' => $customer?->id,
                    ]);
                }

                if (!$customer) {
                    Log::debug('ImportVentaExcelService: buscando cliente por teléfonos', [
                        'telefonos' => $telefonos,
                    ]);

                    $customer = Customer::where(function ($q) use ($telefonos) {
                        foreach ($telefonos as $tel) {
                            $q->orWhere('phone', $tel)
                                ->orWhere('secondary_phone', $tel)
                                ->orWhere('third_phone', $tel);
                        }
                    })->first();

                    Log::debug('ImportVentaExcelService: resultado búsqueda por teléfonos', [
                        'telefonos' => $telefonos,
                        'customer_id' => $customer?->id,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 2) Crear cliente si no existe
                |--------------------------------------------------------------------------
                */
                if (!$customer) {
                    Log::info('ImportVentaExcelService: cliente no encontrado, creando nuevo cliente', [
                        'nombre' => $nombre,
                        'apellidos' => $apellidos,
                        'dni' => $dni,
                        'telefonos' => $telefonos,
                    ]);

                    $customer = Customer::create([
                        'first_names' => $nombre !== '' ? $nombre : 'Nombre',
                        'last_names' => $apellidos !== '' ? $apellidos : 'Apellidos',
                        'phone' => $telefonos[0],
                        'secondary_phone' => $telefonos[1] ?? null,
                        'third_phone' => $telefonos[2] ?? null,
                        'dni' => $dni !== '' ? $dni : null,
                        'primary_address' => $direccion,
                        'provincia' => $provincia,
                        'postal_code' => null,
                        'ciudad' => null,
                    ]);

                    Log::info('ImportVentaExcelService: cliente creado correctamente', [
                        'customer_id' => $customer->id,
                    ]);
                } else {
                    Log::info('ImportVentaExcelService: cliente existente reutilizado', [
                        'customer_id' => $customer->id,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 3) Buscar comercial
                |--------------------------------------------------------------------------
                */
                $comercial = null;

                if ($comercialNombre !== '') {
                    Log::debug('ImportVentaExcelService: buscando comercial', [
                        'comercial_nombre' => $comercialNombre,
                    ]);

                    $comercial = User::query()
                        ->where(function ($q) use ($comercialNombre) {
                            $q->where('name', 'like', "%{$comercialNombre}%")
                                ->orWhere('last_name', 'like', "%{$comercialNombre}%")
                                ->orWhere('empleado_id', 'like', "%{$comercialNombre}%");
                        })
                        ->first();

                    Log::debug('ImportVentaExcelService: resultado búsqueda comercial', [
                        'comercial_nombre' => $comercialNombre,
                        'comercial_encontrado_id' => $comercial?->id,
                    ]);
                } else {
                    Log::warning('ImportVentaExcelService: nombre de comercial vacío en la fila', [
                        'row' => $row,
                    ]);
                }

                $comercialId = $comercial?->id ?? auth()->id();

                Log::info('ImportVentaExcelService: comercial final asignado', [
                    'comercial_id' => $comercialId,
                    'comercial_encontrado_id' => $comercial?->id,
                    'auth_user_id' => auth()->id(),
                    'usa_fallback_auth' => !$comercial,
                ]);

                /*
                |--------------------------------------------------------------------------
                | 4) Crear nota
                |--------------------------------------------------------------------------
                */
                Log::info('ImportVentaExcelService: creando nota', [
                    'customer_id' => $customer->id,
                    'comercial_id' => $comercialId,
                    'fecha_venta' => $fechaVenta->toDateTimeString(),
                ]);

                $nota = Note::create([
                    'user_id' => auth()->id(),
                    'customer_id' => $customer->id,
                    'comercial_id' => $comercialId,
                    'status' => 'contacted',
                    'visit_date' => null,
                    'visit_schedule' => null,
                    'assignment_date' => now(),
                    'show_phone' => true,
                    'de_camino' => false,
                    'estado_terminal' => EstadoTerminal::VENTA,
                    'fuente' => FuenteNotas::EXCEL,
                    'created_at' => $fechaVenta,
                    'updated_at' => $fechaVenta,
                ]);

                Log::info('ImportVentaExcelService: nota creada correctamente', [
                    'nota_id' => $nota->id,
                ]);

                /*
                |--------------------------------------------------------------------------
                | 5) Crear venta
                |--------------------------------------------------------------------------
                */
                Log::info('ImportVentaExcelService: creando venta', [
                    'note_id' => $nota->id,
                    'customer_id' => $customer->id,
                    'comercial_id' => $comercialId,
                    'fecha_venta' => $fechaVenta->toDateTimeString(),
                    'importe_total' => $importe,
                    'origen_venta' => $origenVenta->value,
                ]);

                $venta = Venta::create([
                    'note_id' => $nota->id,
                    'customer_id' => $customer->id,
                    'comercial_id' => $comercialId,
                    'fecha_venta' => $fechaVenta,
                    'importe_total' => $importe,
                    'modalidad_pago' => 'Financiado',
                    'num_cuotas' => 6,
                    'seguimiento' => $seguimiento,
                    'financieras_reparto' => $financieras,
                    'pasadas_financieras' => $pasadas,
                    'estado_venta' => 'en_revision',
                    'origen_venta' => $origenVenta->value,
                    // 'archivo_importacion' => $archivoImportado,
                ]);

                Log::info('ImportVentaExcelService: venta creada correctamente', [
                    'venta_id' => $venta->id,
                    'note_id' => $nota->id,
                    'customer_id' => $customer->id,
                ]);
            });

            Log::debug('ImportVentaExcelService: fila procesada correctamente', [
                'archivo_importado' => $archivoImportado,
                'row' => $row,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImportVentaExcelService: error procesando fila', [
                'archivo_importado' => $archivoImportado,
                'row' => $row,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extraerTelefonos($raw): array
    {
        if (blank($raw)) {
            Log::debug('ImportVentaExcelService: teléfono vacío al extraer teléfonos', [
                'raw' => $raw,
            ]);

            return [];
        }

        $tels = preg_split('/[\/,;|-]/', (string) $raw);

        $resultado = collect($tels)
            ->map(fn($t) => preg_replace('/\D+/', '', (string) $t))
            ->filter(fn($t) => $t !== '')
            ->unique()
            ->values()
            ->toArray();

        Log::debug('ImportVentaExcelService: resultado de extraer teléfonos', [
            'raw' => $raw,
            'resultado' => $resultado,
        ]);

        return $resultado;
    }

    private function parseFechaExcel($value): ?Carbon
    {
        if (blank($value)) {
            Log::debug('ImportVentaExcelService: fecha vacía', [
                'value' => $value,
            ]);

            return null;
        }

        try {
            if (is_numeric($value)) {
                $fecha = Carbon::instance(ExcelDate::excelToDateTimeObject($value));

                Log::debug('ImportVentaExcelService: fecha Excel numérica convertida', [
                    'value' => $value,
                    'fecha' => $fecha->toDateTimeString(),
                ]);

                return $fecha;
            }

            $value = trim((string) $value);

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                $fecha = Carbon::createFromFormat('d/m/Y', $value);

                Log::debug('ImportVentaExcelService: fecha convertida con formato d/m/Y', [
                    'value' => $value,
                    'fecha' => $fecha->toDateTimeString(),
                ]);

                return $fecha;
            }

            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                $fecha = Carbon::createFromFormat('j/n/Y', $value);

                Log::debug('ImportVentaExcelService: fecha convertida con formato j/n/Y', [
                    'value' => $value,
                    'fecha' => $fecha->toDateTimeString(),
                ]);

                return $fecha;
            }

            Log::warning('ImportVentaExcelService: no se pudo interpretar la fecha', [
                'value' => $value,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('ImportVentaExcelService: excepción parseando fecha', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseImporte($value): float
    {
        if (blank($value)) {
            Log::debug('ImportVentaExcelService: importe vacío, se usará 0', [
                'value' => $value,
            ]);

            return 0;
        }

        $original = $value;

        $value = (string) $value;
        $value = str_replace(['€', 'EUR', ' '], '', $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^\d.\-]/', '', $value);

        $resultado = is_numeric($value) ? (float) $value : 0;

        Log::debug('ImportVentaExcelService: importe procesado', [
            'original' => $original,
            'normalizado' => $value,
            'resultado' => $resultado,
        ]);

        return $resultado;
    }

    private function nullIfBlank($value): ?string
    {
        $original = $value;
        $value = is_string($value) ? trim($value) : $value;
        $resultado = blank($value) ? null : (string) $value;

        Log::debug('ImportVentaExcelService: nullIfBlank procesado', [
            'original' => $original,
            'resultado' => $resultado,
        ]);

        return $resultado;
    }
}