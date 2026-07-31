<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Restaura contratos (ventas) desde una BD de backup comparando por nro_contr_adm.
 *
 * Orden: customer → note → venta → venta_ofertas → venta_oferta_productos → transaction_venta.
 * No reutiliza ids de venta del backup (pueden chocar). Sí intenta conservar nro_contr_adm / nro_nota / nro_cliente.
 */
class VentaBackupRecovery
{
    public function __construct(
        private readonly ConnectionInterface $prod,
        private readonly ConnectionInterface $backup,
    ) {}

    public static function make(string $backupConnection = 'mysql_backup'): self
    {
        return new self(DB::connection(), DB::connection($backupConnection));
    }

    /**
     * @return Collection<int, object>
     */
    public function missingVentas(?string $nro = null, ?string $fromDate = null, ?int $limit = null): Collection
    {
        $prodQuery = $this->prod->table('ventas')
            ->whereNotNull('nro_contr_adm')
            ->where('nro_contr_adm', '!=', '');

        if ($this->prodHasColumn('ventas', 'deleted_at')) {
            $prodQuery->whereNull('deleted_at');
        }

        $prodNros = $prodQuery->pluck('nro_contr_adm')->flip();

        $query = $this->backup->table('ventas')
            ->whereNotNull('nro_contr_adm')
            ->where('nro_contr_adm', '!=', '')
            ->orderBy('fecha_venta')
            ->orderBy('id');

        if ($nro) {
            $query->where('nro_contr_adm', $nro);
        }

        if ($fromDate) {
            $query->whereDate('fecha_venta', '>=', $fromDate);
        }

        $missing = $query->get()->values()->filter(
            fn ($row) => ! $prodNros->has($row->nro_contr_adm)
        )->values();

        if ($limit !== null) {
            $missing = $missing->take($limit)->values();
        }

        return $missing;
    }

    /**
     * @return array{status: string, nro: string, message: string, venta_id?: int}
     */
    public function recoverOne(object $backupVenta, bool $dryRun = true): array
    {
        $nro = (string) $backupVenta->nro_contr_adm;

        $existing = $this->prod->table('ventas')->where('nro_contr_adm', $nro)->first();
        if ($existing) {
            if (! empty($existing->deleted_at) && $this->prodHasColumn('ventas', 'deleted_at')) {
                if (! $dryRun) {
                    $this->prod->table('ventas')->where('id', $existing->id)->update([
                        'deleted_at' => null,
                        'deleted_by_user_id' => null,
                        'updated_at' => now(),
                    ]);
                }

                return [
                    'status' => 'restored_soft',
                    'nro' => $nro,
                    'message' => 'Estaba soft-deleted; se restauró',
                    'venta_id' => (int) $existing->id,
                ];
            }

            return [
                'status' => 'skipped',
                'nro' => $nro,
                'message' => 'Ya existe en producción',
                'venta_id' => (int) $existing->id,
            ];
        }

        if ($dryRun) {
            return [
                'status' => 'would_insert',
                'nro' => $nro,
                'message' => sprintf(
                    'Falta (backup venta_id=%s, fecha=%s, cliente_backup=%s)',
                    $backupVenta->id,
                    $backupVenta->fecha_venta ?? '?',
                    $backupVenta->customer_id ?? '?'
                ),
            ];
        }

        try {
            $ventaId = $this->prod->transaction(function () use ($backupVenta) {
                $customerId = $this->ensureCustomer($backupVenta->customer_id);
                $noteId = $this->ensureNote($backupVenta->note_id, $customerId);
                $ventaId = $this->insertVenta($backupVenta, $customerId, $noteId);
                $this->copyOfertas((int) $backupVenta->id, $ventaId);

                return $ventaId;
            });

            return [
                'status' => 'inserted',
                'nro' => $nro,
                'message' => 'Insertado',
                'venta_id' => $ventaId,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'nro' => $nro,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tras insertar A y B, recrea vínculos transaction_venta del backup.
     *
     * @param  array<string, int>  $nroToProdVentaId
     */
    public function relinkTransactionVentas(array $nroToProdVentaId, bool $dryRun = true): int
    {
        if (! $this->backup->getSchemaBuilder()->hasTable('transaction_venta')) {
            return 0;
        }

        if (! $this->prod->getSchemaBuilder()->hasTable('transaction_venta')) {
            return 0;
        }

        $linked = 0;
        $backupIdToNro = $this->backup->table('ventas')
            ->whereNotNull('nro_contr_adm')
            ->pluck('nro_contr_adm', 'id');

        $this->backup->table('transaction_venta')->orderBy('id')->chunk(200, function ($rows) use (
            &$linked,
            $backupIdToNro,
            $nroToProdVentaId,
            $dryRun
        ) {
            foreach ($rows as $row) {
                $nroA = $backupIdToNro[$row->id_contrato] ?? null;
                $nroB = $backupIdToNro[$row->id_contrato_asoc] ?? null;
                if (! $nroA || ! $nroB) {
                    continue;
                }

                $prodA = $nroToProdVentaId[$nroA]
                    ?? $this->prod->table('ventas')->where('nro_contr_adm', $nroA)->value('id');
                $prodB = $nroToProdVentaId[$nroB]
                    ?? $this->prod->table('ventas')->where('nro_contr_adm', $nroB)->value('id');

                if (! $prodA || ! $prodB) {
                    continue;
                }

                $exists = $this->prod->table('transaction_venta')
                    ->where('id_contrato', $prodA)
                    ->where('id_contrato_asoc', $prodB)
                    ->exists();

                if ($exists) {
                    continue;
                }

                if (! $dryRun) {
                    $payload = [
                        'id_contrato' => $prodA,
                        'id_contrato_asoc' => $prodB,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                    if ($this->prodHasColumn('transaction_venta', 'deleted_at')) {
                        $payload['deleted_at'] = null;
                    }
                    $this->prod->table('transaction_venta')->insert($payload);
                }
                $linked++;
            }
        });

        return $linked;
    }

    private function ensureCustomer(?int $backupCustomerId): ?int
    {
        if (! $backupCustomerId) {
            return null;
        }

        $backup = $this->backup->table('customers')->where('id', $backupCustomerId)->first();
        if (! $backup) {
            return null;
        }

        if (! empty($backup->dni)) {
            $byDni = $this->prod->table('customers')
                ->where('dni', $backup->dni)
                ->when($this->prodHasColumn('customers', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->first();
            if ($byDni) {
                return (int) $byDni->id;
            }
        }

        if (! empty($backup->nro_cliente)) {
            $byNro = $this->prod->table('customers')
                ->where('nro_cliente', $backup->nro_cliente)
                ->when($this->prodHasColumn('customers', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->first();
            if ($byNro) {
                return (int) $byNro->id;
            }
        }

        $payload = $this->filterToTableColumns('customers', (array) $backup);
        unset($payload['id']);
        if (array_key_exists('deleted_at', $payload)) {
            $payload['deleted_at'] = null;
        }
        if (array_key_exists('deleted_by_user_id', $payload)) {
            $payload['deleted_by_user_id'] = null;
        }
        if (array_key_exists('postal_code_id', $payload) && $payload['postal_code_id']) {
            if (
                ! $this->prod->getSchemaBuilder()->hasTable('postal_codes')
                || ! $this->prod->table('postal_codes')->where('id', $payload['postal_code_id'])->exists()
            ) {
                $payload['postal_code_id'] = null;
            }
        }

        return (int) $this->prod->table('customers')->insertGetId($payload);
    }

    private function ensureNote(?int $backupNoteId, ?int $prodCustomerId): ?int
    {
        if (! $backupNoteId) {
            return null;
        }

        $backup = $this->backup->table('notes')->where('id', $backupNoteId)->first();
        if (! $backup) {
            return null;
        }

        if (! empty($backup->nro_nota)) {
            $byNro = $this->prod->table('notes')
                ->where('nro_nota', $backup->nro_nota)
                ->when($this->prodHasColumn('notes', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->first();
            if ($byNro) {
                return (int) $byNro->id;
            }
        }

        $payload = $this->filterToTableColumns('notes', (array) $backup);
        unset($payload['id']);
        $payload['customer_id'] = $prodCustomerId;
        if (array_key_exists('deleted_at', $payload)) {
            $payload['deleted_at'] = null;
        }
        if (array_key_exists('deleted_by_user_id', $payload)) {
            $payload['deleted_by_user_id'] = null;
        }
        $payload['comercial_id'] = $this->nullableUserId($payload['comercial_id'] ?? null);
        $payload['user_id'] = $this->nullableUserId($payload['user_id'] ?? null) ?? $payload['comercial_id'];

        return (int) $this->prod->table('notes')->insertGetId($payload);
    }

    private function insertVenta(object $backupVenta, ?int $customerId, ?int $noteId): int
    {
        $payload = $this->filterToTableColumns('ventas', (array) $backupVenta);
        unset($payload['id']);
        $payload['customer_id'] = $customerId;
        $payload['note_id'] = $noteId;
        if (array_key_exists('deleted_at', $payload)) {
            $payload['deleted_at'] = null;
        }
        if (array_key_exists('deleted_by_user_id', $payload)) {
            $payload['deleted_by_user_id'] = null;
        }

        foreach (['comercial_id', 'companion_id', 'repartidor_id', 'repartidor_2'] as $userCol) {
            if (array_key_exists($userCol, $payload)) {
                $payload[$userCol] = $this->nullableUserId($payload[$userCol] ?? null);
            }
        }

        return (int) $this->prod->table('ventas')->insertGetId($payload);
    }

    private function copyOfertas(int $backupVentaId, int $prodVentaId): void
    {
        if (! $this->backup->getSchemaBuilder()->hasTable('venta_ofertas')) {
            return;
        }

        $ofertas = $this->backup->table('venta_ofertas')->where('venta_id', $backupVentaId)->get();
        foreach ($ofertas as $oferta) {
            if (! $this->prod->table('ofertas')->where('id', $oferta->oferta_id)->exists()) {
                continue;
            }

            $voPayload = $this->filterToTableColumns('venta_ofertas', (array) $oferta);
            unset($voPayload['id']);
            $voPayload['venta_id'] = $prodVentaId;
            $newVoId = (int) $this->prod->table('venta_ofertas')->insertGetId($voPayload);

            if (! $this->backup->getSchemaBuilder()->hasTable('venta_oferta_productos')) {
                continue;
            }

            $productos = $this->backup->table('venta_oferta_productos')
                ->where('venta_oferta_id', $oferta->id)
                ->get();

            foreach ($productos as $producto) {
                if (! $this->prod->table('productos')->where('id', $producto->producto_id)->exists()) {
                    continue;
                }
                $pPayload = $this->filterToTableColumns('venta_oferta_productos', (array) $producto);
                unset($pPayload['id']);
                $pPayload['venta_oferta_id'] = $newVoId;
                $this->prod->table('venta_oferta_productos')->insert($pPayload);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function filterToTableColumns(string $table, array $row): array
    {
        $columns = Schema::connection($this->prod->getName())->getColumnListing($table);

        return array_intersect_key($row, array_flip($columns));
    }

    private function prodHasColumn(string $table, string $column): bool
    {
        return Schema::connection($this->prod->getName())->hasColumn($table, $column);
    }

    private function nullableUserId(mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $id = (int) $userId;
        if ($id <= 0) {
            return null;
        }

        return $this->prod->table('users')->where('id', $id)->exists() ? $id : null;
    }
}
