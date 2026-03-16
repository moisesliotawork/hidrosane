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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportVentaExcelService
{
    public function procesarFila(
        array $row,
        OrigenVenta $origenVenta = OrigenVenta::VENTA_NORMAL,
        ?string $archivoImportado = null
    ): void {

        $fechaVenta = $this->parseFechaExcel($row[0] ?? null);

        // Saltar fila si la fecha no se pudo interpretar
        if (!$fechaVenta) {
            return;
        }

        $telefonoRaw = $row[5] ?? null;
        $telefonos = $this->extraerTelefonos($telefonoRaw);

        // Saltar fila si no hay teléfono
        if (empty($telefonos)) {
            return;
        }

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

            /*
            |--------------------------------------------------------------------------
            | 1) Buscar cliente
            |--------------------------------------------------------------------------
            */
            $customer = null;

            if ($dni !== '') {
                $customer = Customer::where('dni', $dni)->first();
            }

            if (!$customer) {
                $customer = Customer::where(function ($q) use ($telefonos) {
                    foreach ($telefonos as $tel) {
                        $q->orWhere('phone', $tel)
                            ->orWhere('secondary_phone', $tel)
                            ->orWhere('third_phone', $tel);
                    }
                })->first();
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Crear cliente si no existe
            |--------------------------------------------------------------------------
            */
            if (!$customer) {
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
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Buscar comercial
            |--------------------------------------------------------------------------
            */
            $comercial = null;

            if ($comercialNombre !== '') {
                $comercial = User::query()
                    ->where(function ($q) use ($comercialNombre) {
                        $q->where('name', 'like', "%{$comercialNombre}%")
                            ->orWhere('last_name', 'like', "%{$comercialNombre}%")
                            ->orWhere('empleado_id', 'like', "%{$comercialNombre}%");
                    })
                    ->first();
            }

            $comercialId = $comercial?->id ?? auth()->id();

            /*
            |--------------------------------------------------------------------------
            | 4) Crear nota
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | 5) Crear venta
            |--------------------------------------------------------------------------
            */
            Venta::create([
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

                // 🔹 NUEVO
                'origen_venta' => $origenVenta->value,

                // 🔹 opcional si agregas columna
                // 'archivo_importacion' => $archivoImportado,
            ]);
        });
    }

    private function extraerTelefonos($raw): array
    {
        if (blank($raw)) {
            return [];
        }

        $tels = preg_split('/[\/,;|-]/', (string) $raw);

        return collect($tels)
            ->map(fn($t) => preg_replace('/\D+/', '', (string) $t))
            ->filter(fn($t) => $t !== '')
            ->unique()
            ->values()
            ->toArray();
    }

    private function parseFechaExcel($value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {

            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            }

            $value = trim((string) $value);

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value);
            }

            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('j/n/Y', $value);
            }

            return null;

        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseImporte($value): float
    {
        if (blank($value)) {
            return 0;
        }

        $value = (string) $value;
        $value = str_replace(['€', 'EUR', ' '], '', $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^\d.\-]/', '', $value);

        return is_numeric($value) ? (float) $value : 0;
    }

    private function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        return blank($value) ? null : (string) $value;
    }
}