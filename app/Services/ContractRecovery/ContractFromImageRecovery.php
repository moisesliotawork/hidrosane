<?php

namespace App\Services\ContractRecovery;

use App\Enums\EstadoVenta;
use App\Models\ContratoMesVariacionItem;
use App\Models\ContratoRecuperado;
use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Models\User;
use App\Models\Venta;
use App\Support\ContratosPorMesStats;
use App\Support\VentaSoftRestore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Crea/restaura una venta SOLO desde SuperAdmin recovery.
 * No altera customers (salvo checkbox IBAN), no usa formularios comerciales.
 */
final class ContractFromImageRecovery
{
    /**
     * @return array{ok: bool, message: string, venta_id?: int}
     */
    public function addContract(ContratoRecoveryItem $item, bool $updateCustomerIban = false): array
    {
        if ($item->status === ContratoRecoveryItem::STATUS_ADDED && $item->venta_id) {
            return [
                'ok' => false,
                'message' => 'Este registro ya tiene contrato agregado (#'.$item->venta_id.').',
                'venta_id' => (int) $item->venta_id,
            ];
        }

        $data = $item->reviewedData();
        $dni = $this->normalizeDni((string) ($data['dni'] ?? $item->dni ?? ''));
        $nro = trim((string) ($data['nro_contr_adm'] ?? $item->nro_contr_adm ?? ''));

        if ($dni === '') {
            return ['ok' => false, 'message' => 'Falta DNI para enlazar el cliente.'];
        }

        if ($nro === '') {
            return ['ok' => false, 'message' => 'Falta nº de contrato admin.'];
        }

        $customer = Customer::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
            ->orderBy('id')
            ->first();

        if (! $customer) {
            return ['ok' => false, 'message' => "No hay cliente activo con DNI {$dni}. Los clientes no se crean desde aquí."];
        }

        $comercialId = (int) ($item->comercial_id ?: ($data['comercial_id'] ?? 0));
        if ($comercialId <= 0) {
            $comercialId = $this->resolveComercialId($data['comercial_codes'] ?? null);
        }
        if ($comercialId <= 0) {
            return ['ok' => false, 'message' => 'Indica un comercial (obligatorio en ventas). Selecciónalo en la revisión o en la fila.'];
        }

        try {
            $venta = DB::transaction(function () use ($item, $customer, $nro, $data, $comercialId, $updateCustomerIban) {
                $existing = Venta::withTrashed()->where('nro_contr_adm', $nro)->first();

                if ($existing && ! $existing->trashed()) {
                    throw new RuntimeException("Ya existe un contrato activo con nº admin {$nro} (id {$existing->id}).");
                }

                if ($existing && $existing->trashed()) {
                    VentaSoftRestore::restore($existing);
                    $venta = $existing->fresh() ?? $existing;
                    $this->applyFields($venta, $customer, $data, $comercialId, overwriteNro: false);
                    $venta->save();
                    $createdNew = false;
                } else {
                    $venta = new Venta;
                    $this->applyFields($venta, $customer, $data, $comercialId, overwriteNro: true);
                    $venta->nro_contr_adm = $nro;
                    $venta->save();
                    $createdNew = true;
                }

                $this->attachDocuments($venta, $item->documents ?? []);

                if ($updateCustomerIban && filled($data['iban'] ?? null)) {
                    $customer->forceFill([
                        'iban' => preg_replace('/\s+/', '', (string) $data['iban']),
                    ])->saveQuietly();
                }

                ContratoRecuperado::query()->firstOrCreate(
                    ['nro_contr_adm' => $nro],
                    ['created_by_user_id' => auth()->id()],
                );

                if ($createdNew) {
                    ContratosPorMesStats::recordVariationItem(
                        $venta->fresh() ?? $venta,
                        ContratoMesVariacionItem::ESTADO_NUEVO,
                        auth()->id(),
                    );
                }

                $item->forceFill([
                    'status' => ContratoRecoveryItem::STATUS_ADDED,
                    'customer_id' => $customer->id,
                    'venta_id' => $venta->id,
                    'comercial_id' => $comercialId,
                    'dni' => $this->normalizeDni((string) ($data['dni'] ?? '')),
                    'nro_contr_adm' => $nro,
                    'last_error' => null,
                ])->save();

                return $venta;
            });

            return [
                'ok' => true,
                'message' => "Contrato {$nro} agregado (venta #{$venta->id}).",
                'venta_id' => (int) $venta->id,
            ];
        } catch (Throwable $e) {
            $item->forceFill([
                'status' => ContratoRecoveryItem::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyFields(Venta $venta, Customer $customer, array $data, int $comercialId, bool $overwriteNro): void
    {
        $fechaVenta = $this->parseDate($data['fecha_venta'] ?? null) ?? now();
        $fechaEntrega = $this->parseDate($data['fecha_entrega'] ?? null);

        $productos = [];
        if (filled($data['productos_texto'] ?? null)) {
            $productos = [
                ['descripcion' => (string) $data['productos_texto']],
            ];
        }

        $venta->forceFill([
            'customer_id' => $customer->id,
            'comercial_id' => $comercialId,
            'fecha_venta' => $fechaVenta,
            'fecha_entrega' => $fechaEntrega,
            'horario_entrega' => $data['horario_entrega'] ?? null,
            'importe_total' => $this->toMoney($data['importe_total'] ?? null) ?? 0,
            'entrada' => $this->toMoney($data['entrada'] ?? null) ?? 0,
            'cuota_mensual' => $this->toMoney($data['cuota_mensual'] ?? null),
            'num_cuotas' => isset($data['num_cuotas']) ? (int) $data['num_cuotas'] : null,
            'total_final' => $this->toMoney($data['importe_total'] ?? null) ?? 0,
            'productos_externos' => $productos ?: null,
            'list_descripcion' => 'Recuperado desde imagen (SuperAdmin)',
            'en_app' => false,
            'estado_venta' => EstadoVenta::EN_REVISION,
            'nro_cliente_adm' => $customer->nro_cliente,
            'observaciones_repartidor' => $data['observaciones'] ?? null,
        ]);

        if ($overwriteNro && filled($data['nro_contr_adm'] ?? null)) {
            $venta->nro_contr_adm = trim((string) $data['nro_contr_adm']);
        }
    }

    /**
     * @param  list<array{type?: string, path?: string}>|null  $documents
     */
    protected function attachDocuments(Venta $venta, ?array $documents): void
    {
        if (! $documents) {
            return;
        }

        $updates = [];
        $otros = [];

        foreach ($documents as $doc) {
            $path = (string) ($doc['path'] ?? '');
            $type = (string) ($doc['type'] ?? 'other');
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $dest = 'ventas/'.now()->format('YmdHis').'_recovery_'.$venta->id.'_'.Str::random(6).'.'.$ext;
            Storage::disk('public')->put($dest, Storage::disk('local')->get($path));

            if ($type === ContractImageExtractor::TYPE_APP && empty($updates['contrato_firmado'])) {
                $updates['contrato_firmado'] = $dest;
            } elseif ($type === ContractImageExtractor::TYPE_ALBARAN && empty($updates['precontractual'])) {
                $updates['precontractual'] = $dest;
            } else {
                $otros[] = $dest;
            }
        }

        if ($otros) {
            $existing = $venta->otros_documentos;
            if (is_string($existing) && $existing !== '') {
                $otros = array_merge([$existing], $otros);
            } elseif (is_array($existing)) {
                $otros = array_merge($existing, $otros);
            }
            // otros_documentos en el modelo suele ser string path; guardar el primero y el resto no forzar JSON si columna es string
            $updates['otros_documentos'] = $otros[0] ?? null;
            if (count($otros) > 1 && empty($updates['foto_sorteo'])) {
                $updates['foto_sorteo'] = $otros[1];
            }
        }

        if ($updates) {
            $venta->forceFill($updates)->saveQuietly();
        }
    }

    protected function resolveComercialId(mixed $codes): ?int
    {
        if (! is_string($codes) && ! is_array($codes)) {
            return null;
        }

        $parts = is_array($codes)
            ? $codes
            : (preg_split('/[,\s\-]+/', (string) $codes) ?: []);
        foreach ($parts as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $padded = str_pad(ltrim($code, '0') ?: '0', 3, '0', STR_PAD_LEFT);
            $user = User::query()
                ->where('empleado_id', $code)
                ->orWhere('empleado_id', $padded)
                ->orWhere('empleado_id', ltrim($code, '0'))
                ->first();
            if ($user) {
                return (int) $user->id;
            }
        }

        return null;
    }

    protected function normalizeDni(string $dni): string
    {
        return mb_strtoupper(trim($dni));
    }

    protected function parseDate(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value);
        } catch (Throwable) {
            foreach (['d-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y', 'Y-m-d'] as $fmt) {
                try {
                    return \Illuminate\Support\Carbon::createFromFormat($fmt, (string) $value);
                } catch (Throwable) {
                }
            }
        }

        return null;
    }

    protected function toMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        $s = str_replace(['€', ' ', '.'], ['', '', ''], (string) $value);
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? round((float) $s, 2) : null;
    }
}
