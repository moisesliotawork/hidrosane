<?php

namespace App\Services;

use App\Models\CommercialPhoneLog;
use App\Models\Customer;
use App\Models\CustomerObservation;
use App\Models\Note;
use App\Models\Scopes\NotMergedScope;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerPrimaryKeyReassignmentService
{
    public static function reassign(Customer $customer, int $newId): void
    {
        $oldId = $customer->id;

        if ($oldId === $newId) {
            return;
        }

        if ($newId < 1) {
            throw new \InvalidArgumentException('El ID_Cliente debe ser un número mayor que cero.');
        }

        $idTaken = Customer::withoutGlobalScope(NotMergedScope::class)
            ->whereKey($newId)
            ->exists();

        if ($idTaken) {
            throw new \RuntimeException("Ya existe un cliente con ID_Cliente {$newId}.");
        }

        DB::transaction(function () use ($oldId, $newId) {
            Schema::disableForeignKeyConstraints();

            try {
                Note::query()->where('customer_id', $oldId)->update(['customer_id' => $newId]);
                Venta::query()->where('customer_id', $oldId)->update(['customer_id' => $newId]);
                CustomerObservation::query()->where('customer_id', $oldId)->update(['customer_id' => $newId]);
                CommercialPhoneLog::query()->where('customer_id', $oldId)->update(['customer_id' => $newId]);

                Customer::withoutGlobalScope(NotMergedScope::class)
                    ->where('merged_into_id', $oldId)
                    ->update(['merged_into_id' => $newId]);

                DB::table('customers')->where('id', $oldId)->update(['id' => $newId]);
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });
    }
}
