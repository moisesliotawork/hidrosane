<?php

namespace App\Support;

use App\Models\Customer;
use RuntimeException;

final class CustomerSoftDelete
{
    /**
     * Archiva el cliente (soft delete). No elimina notas, contratos ni datos relacionados.
     */
    public static function delete(Customer $customer, ?int $deletedByUserId = null): void
    {
        $customer->forceFill([
            'deleted_by_user_id' => $deletedByUserId ?? auth()->id(),
        ])->saveQuietly();

        // SoftDeletes::delete() solo marca deleted_at; no dispara CASCADE de BD.
        $customer->delete();
    }

    public static function assertNotForceDeletable(): void
    {
        throw new RuntimeException(
            'Los clientes no pueden eliminarse definitivamente. Use archivar y recupere desde Clientes borrados.'
        );
    }
}
