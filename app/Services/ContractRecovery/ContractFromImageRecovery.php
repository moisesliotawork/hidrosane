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
 *
 * GARANTÍAS DE SEGURIDAD (contratos no se pierden):
 * - Nunca llama delete / forceDelete / soft-delete sobre ventas.
 * - Si ya existe un contrato ACTIVO con ese nº admin → aborta (no toca nada).
 * - Si existe SOFT-DELETED → solo restaura si el cliente del contrato coincide con el DNI
 *   recuperado; no reasigna el contrato a otro cliente.
 * - Al adjuntar docs, no sobrescribe rutas de documentos ya existentes.
 * - No modifica otras ventas distintas del nº admin objetivo.
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
        $nro = $this->normalizeNro((string) ($data['nro_contr_adm'] ?? $item->nro_contr_adm ?? ''));

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
            $comercialId = (int) ($this->resolveComercialId($data['comercial_codes'] ?? null) ?? 0);
        }
        if ($comercialId <= 0) {
            return ['ok' => false, 'message' => 'Indica un comercial (obligatorio en ventas). Selecciónalo en Editar.'];
        }

        try {
            $venta = DB::transaction(function () use ($item, $customer, $nro, $data, $comercialId, $updateCustomerIban, $dni) {
                // Bloqueo pesimista: evita carreras que dupliquen / pisen contratos
                $existing = $this->findVentaByNroLocked($nro);

                if ($existing && ! $existing->trashed()) {
                    throw new RuntimeException(
                        "PROTEGIDO: ya existe un contrato ACTIVO con nº admin «{$nro}» (venta #{$existing->id}). ".
                        'No se modifica ni se borra. Usa otro nº o revisa el existente.'
                    );
                }

                if ($existing && $existing->trashed()) {
                    $this->assertSoftDeletedBelongsToSameCustomer($existing, $customer, $dni, $nro);

                    VentaSoftRestore::restore($existing);
                    $venta = Venta::query()->whereKey($existing->id)->lockForUpdate()->first()
                        ?? $existing->fresh()
                        ?? $existing;

                    // Solo rellena campos vacíos / metadatos de recuperación; no cambia nº ni cliente ajeno
                    $this->applyFieldsSafely($venta, $customer, $data, $comercialId, isNew: false);
                    $venta->save();
                    $createdNew = false;
                } else {
                    // Doble check por variantes de padding antes de insertar
                    $collision = $this->findAnyNroCollision($nro);
                    if ($collision) {
                        throw new RuntimeException(
                            "PROTEGIDO: el nº «{$nro}» colisiona con venta #{$collision->id} ".
                            "(nro_contr_adm={$collision->nro_contr_adm}".($collision->trashed() ? ', archivado' : ', activo').'). '.
                            'No se crea ni se borra nada.'
                        );
                    }

                    $venta = new Venta;
                    $this->applyFieldsSafely($venta, $customer, $data, $comercialId, isNew: true);
                    $venta->nro_contr_adm = $nro;
                    $venta->save();
                    $createdNew = true;

                    // Verificación post-insert: el nº sigue siendo el nuestro
                    $venta->refresh();
                    if ($this->normalizeNro((string) $venta->nro_contr_adm) !== $nro) {
                        throw new RuntimeException(
                            'PROTEGIDO: el nº de contrato cambió al guardar. Se aborta la transacción (rollback).'
                        );
                    }
                }

                $this->attachDocumentsWithoutOverwrite($venta, $item->documents ?? []);

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
                    'dni' => $dni,
                    'nro_contr_adm' => $nro,
                    'last_error' => null,
                ])->save();

                return $venta;
            });

            return [
                'ok' => true,
                'message' => "Contrato {$nro} agregado con seguridad (venta #{$venta->id}). Ningún otro contrato fue borrado.",
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

    protected function findVentaByNroLocked(string $nro): ?Venta
    {
        $candidates = $this->nroCandidates($nro);

        return Venta::withTrashed()
            ->whereIn('nro_contr_adm', $candidates)
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    protected function findAnyNroCollision(string $nro): ?Venta
    {
        return Venta::withTrashed()
            ->whereIn('nro_contr_adm', $this->nroCandidates($nro))
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    protected function nroCandidates(string $nro): array
    {
        $nro = $this->normalizeNro($nro);
        $out = [$nro];

        if (ctype_digit($nro)) {
            $out[] = ltrim($nro, '0') ?: '0';
            $out[] = str_pad((string) (int) $nro, 5, '0', STR_PAD_LEFT);
            $out[] = str_pad((string) (int) $nro, 4, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique(array_filter($out, fn ($v) => $v !== '')));
    }

    protected function assertSoftDeletedBelongsToSameCustomer(
        Venta $existing,
        Customer $customer,
        string $dni,
        string $nro,
    ): void {
        if (! $existing->customer_id) {
            return;
        }

        if ((int) $existing->customer_id === (int) $customer->id) {
            return;
        }

        $existing->loadMissing('customer');
        $existingDni = $this->normalizeDni((string) ($existing->customer?->dni ?? ''));

        if ($existingDni !== '' && $existingDni === $dni) {
            return;
        }

        throw new RuntimeException(
            "PROTEGIDO: el contrato archivado «{$nro}» (venta #{$existing->id}) pertenece a otro cliente ".
            "(customer_id={$existing->customer_id}".($existingDni !== '' ? ", DNI {$existingDni}" : '').'). '.
            "El DNI recuperado es {$dni}. No se restaura ni se reasigna. Ningún contrato se borra."
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyFieldsSafely(
        Venta $venta,
        Customer $customer,
        array $data,
        int $comercialId,
        bool $isNew,
    ): void {
        $fechaVenta = $this->parseDate($data['fecha_venta'] ?? null) ?? ($isNew ? now() : $venta->fecha_venta);
        $fechaEntrega = $this->parseDate($data['fecha_entrega'] ?? null);

        $productos = null;
        if (filled($data['productos_texto'] ?? null)) {
            $productos = [
                ['descripcion' => (string) $data['productos_texto']],
            ];
        }

        $repartidorId = (int) ($data['repartidor_id'] ?? 0);
        if ($repartidorId <= 0) {
            $repartidorId = (int) ($this->resolveComercialId($data['repartidor_code'] ?? null) ?? 0);
        }

        if ($isNew) {
            $venta->forceFill([
                'customer_id' => $customer->id,
                'comercial_id' => $comercialId,
                'repartidor_id' => $repartidorId > 0 ? $repartidorId : null,
                'fecha_venta' => $fechaVenta,
                'fecha_entrega' => $fechaEntrega,
                'horario_entrega' => $data['horario_entrega'] ?? null,
                'importe_total' => $this->toMoney($data['importe_total'] ?? null) ?? 0,
                'entrada' => $this->toMoney($data['entrada'] ?? null) ?? 0,
                'cuota_mensual' => $this->toMoney($data['cuota_mensual'] ?? null),
                'num_cuotas' => isset($data['num_cuotas']) ? (int) $data['num_cuotas'] : null,
                'total_final' => $this->toMoney($data['importe_total'] ?? null) ?? 0,
                'productos_externos' => $productos,
                'list_descripcion' => 'Recuperado desde imagen (SuperAdmin)',
                'en_app' => false,
                'estado_venta' => EstadoVenta::EN_REVISION,
                'nro_cliente_adm' => $customer->nro_cliente,
                'observaciones_repartidor' => $data['observaciones'] ?? null,
            ]);

            return;
        }

        // Restore path: NUNCA cambia nro_contr_adm ni customer_id si ya hay uno distinto
        $patch = [
            'list_descripcion' => $venta->list_descripcion ?: 'Recuperado desde imagen (SuperAdmin)',
        ];

        if (! $venta->customer_id) {
            $patch['customer_id'] = $customer->id;
        }

        if (! $venta->comercial_id) {
            $patch['comercial_id'] = $comercialId;
        }

        if (! $venta->repartidor_id && $repartidorId > 0) {
            $patch['repartidor_id'] = $repartidorId;
        }

        if (! $venta->fecha_venta && $fechaVenta) {
            $patch['fecha_venta'] = $fechaVenta;
        }

        if (! $venta->fecha_entrega && $fechaEntrega) {
            $patch['fecha_entrega'] = $fechaEntrega;
        }

        if (blank($venta->horario_entrega) && filled($data['horario_entrega'] ?? null)) {
            $patch['horario_entrega'] = $data['horario_entrega'];
        }

        if ((float) ($venta->importe_total ?? 0) <= 0 && $this->toMoney($data['importe_total'] ?? null)) {
            $money = $this->toMoney($data['importe_total']);
            $patch['importe_total'] = $money;
            $patch['total_final'] = $money;
        }

        if ((float) ($venta->entrada ?? 0) <= 0 && $this->toMoney($data['entrada'] ?? null) !== null) {
            $patch['entrada'] = $this->toMoney($data['entrada']);
        }

        if (! $venta->cuota_mensual && $this->toMoney($data['cuota_mensual'] ?? null) !== null) {
            $patch['cuota_mensual'] = $this->toMoney($data['cuota_mensual']);
        }

        if (! $venta->num_cuotas && isset($data['num_cuotas'])) {
            $patch['num_cuotas'] = (int) $data['num_cuotas'];
        }

        if (blank($venta->productos_externos) && $productos) {
            $patch['productos_externos'] = $productos;
        }

        if (blank($venta->observaciones_repartidor) && filled($data['observaciones'] ?? null)) {
            $patch['observaciones_repartidor'] = $data['observaciones'];
        }

        $venta->forceFill($patch);
    }

    /**
     * Solo rellena campos de documento VACÍOS. Nunca pisa un archivo ya guardado.
     *
     * @param  list<array{type?: string, path?: string}>|null  $documents
     */
    protected function attachDocumentsWithoutOverwrite(Venta $venta, ?array $documents): void
    {
        if (! $documents) {
            return;
        }

        $updates = [];

        foreach ($documents as $doc) {
            $path = (string) ($doc['path'] ?? '');
            $type = (string) ($doc['type'] ?? 'other');
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $dest = 'ventas/'.now()->format('YmdHis').'_recovery_'.$venta->id.'_'.Str::random(6).'.'.$ext;
            Storage::disk('public')->put($dest, Storage::disk('local')->get($path));

            if ($type === ContractImageExtractor::TYPE_APP && blank($venta->contrato_firmado) && empty($updates['contrato_firmado'])) {
                $updates['contrato_firmado'] = $dest;
            } elseif ($type === ContractImageExtractor::TYPE_ALBARAN && blank($venta->precontractual) && empty($updates['precontractual'])) {
                $updates['precontractual'] = $dest;
            } elseif (blank($venta->otros_documentos) && empty($updates['otros_documentos'])) {
                $updates['otros_documentos'] = $dest;
            } elseif (blank($venta->foto_sorteo) && empty($updates['foto_sorteo'])) {
                $updates['foto_sorteo'] = $dest;
            }
            // Si todos los slots están ocupados, se deja el archivo en disco público
            // pero NO se sobrescribe ninguna ruta de la venta.
        }

        if ($updates !== []) {
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

    protected function normalizeNro(string $nro): string
    {
        return trim($nro);
    }

    protected function parseDate(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (! filled($value)) {
            return null;
        }

        $raw = trim((string) $value);
        // Fechas del contrato impreso son europeas (Fec.Promo. / Fec.Entr. = d-m-Y)
        foreach (['d-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y', 'Y-m-d'] as $fmt) {
            try {
                $dt = \Illuminate\Support\Carbon::createFromFormat($fmt, $raw);
                if ($dt !== false) {
                    return $dt;
                }
            } catch (Throwable) {
            }
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
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
