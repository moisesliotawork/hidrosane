<?php

namespace App\Services;

use App\Models\CommercialPhoneLog;
use App\Models\Customer;
use App\Models\CustomerObservation;
use App\Models\Note;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class CustomerMergeService
{
    /**
     * Fusiona customers por coincidencia exacta de teléfono.
     *
     * Reglas:
     * - keeper: el más antiguo por created_at
     * - sourceData: el más recientemente actualizado por updated_at
     * - mueve notas y ventas al keeper
     * - actualiza datos del keeper usando sourceData
     * - marca los demás como fusionados, no los elimina
     */
    public function mergeByPhone(string $phone, ?int $mergedByUserId = null): array
    {
        return DB::transaction(function () use ($phone, $mergedByUserId) {
            $customers = Customer::query()
                ->whereNull('merged_into_id')
                ->where(function ($query) use ($phone) {
                    $query->where('phone', $phone)
                        ->orWhere('secondary_phone', $phone)
                        ->orWhere('third_phone', $phone)
                        ->orWhere('phone1_commercial', $phone)
                        ->orWhere('phone2_commercial', $phone);
                })
                ->lockForUpdate()
                ->get();

            if ($customers->count() < 2) {
                throw new \RuntimeException('No hay suficientes customers activos para fusionar.');
            }

            /** @var Customer $keeper */
            $keeper = $customers
                ->sortBy(fn(Customer $c) => [
                    optional($c->created_at)->timestamp ?? PHP_INT_MAX,
                    $c->id,
                ])
                ->first();

            /** @var Customer $sourceData */
            $sourceData = $customers
                ->sortByDesc(fn(Customer $c) => [
                    optional($c->updated_at)->timestamp ?? 0,
                    $c->id,
                ])
                ->first();

            $duplicateIds = $customers
                ->pluck('id')
                ->filter(fn($id) => $id !== $keeper->id)
                ->values()
                ->all();

            $notesUpdated = 0;
            $ventasUpdated = 0;

            if (!empty($duplicateIds)) {
                $notesUpdated = Note::query()
                    ->whereIn('customer_id', $duplicateIds)
                    ->update([
                        'customer_id' => $keeper->id,
                    ]);

                $ventasUpdated = Venta::query()
                    ->whereIn('customer_id', $duplicateIds)
                    ->update([
                        'customer_id' => $keeper->id,
                    ]);
            }

            $payload = $this->buildPayloadFromLatestUpdated($sourceData, $keeper, $phone);

            $keeper->fill($payload);
            $keeper->save();

            Customer::query()
                ->whereIn('id', $duplicateIds)
                ->update([
                    'merged_into_id' => $keeper->id,
                    'merged_at' => now(),
                    'merged_by_user_id' => $mergedByUserId,
                ]);

            return [
                'keeper_id' => $keeper->id,
                'source_data_id' => $sourceData->id,
                'merged_ids' => $duplicateIds,
                'notes_updated' => $notesUpdated,
                'ventas_updated' => $ventasUpdated,
            ];
        });
    }

    /**
     * Fusiona dos customers por ID.
     * - keeper: se preserva y recibe todos los datos y registros del toDelete
     * - toDelete: se borra definitivamente tras mover sus datos
     * - Los campos vacíos del keeper se rellenan con los del toDelete
     */
    public function mergeByIds(int $keeperId, int $toDeleteId, ?int $mergedByUserId = null): array
    {
        return DB::transaction(function () use ($keeperId, $toDeleteId, $mergedByUserId) {
            $keeper   = Customer::lockForUpdate()->findOrFail($keeperId);
            $toDelete = Customer::lockForUpdate()->findOrFail($toDeleteId);

            // Reasignar registros relacionados al keeper
            $notesUpdated  = Note::where('customer_id', $toDeleteId)->update(['customer_id' => $keeperId]);
            $ventasUpdated = Venta::where('customer_id', $toDeleteId)->update(['customer_id' => $keeperId]);
            CustomerObservation::where('customer_id', $toDeleteId)->update(['customer_id' => $keeperId]);
            CommercialPhoneLog::where('customer_id', $toDeleteId)->update(['customer_id' => $keeperId]);

            // Si algún customer apuntaba al toDelete como merged_into, apuntar al keeper
            Customer::where('merged_into_id', $toDeleteId)->update(['merged_into_id' => $keeperId]);

            // Completar campos vacíos del keeper con los del toDelete
            $fields = [
                'first_names', 'last_names', 'phone', 'secondary_phone', 'third_phone',
                'phone1_commercial', 'phone2_commercial', 'email', 'nro_piso',
                'postal_code_id', 'primary_address', 'secondary_address', 'parish', 'dni',
                'fecha_nac', 'iban', 'tipo_vivienda', 'estado_civil', 'situacion_laboral',
                'ingresos_rango', 'num_hab_casa', 'ayuntamiento', 'edadTelOp',
                'postal_code', 'ciudad', 'provincia', 'antiguedad', 'nombre_empresa', 'oficio',
            ];

            foreach ($fields as $field) {
                if (blank($keeper->$field) && filled($toDelete->$field)) {
                    $keeper->$field = $toDelete->$field;
                }
            }
            $keeper->save();

            // Eliminar definitivamente el duplicado
            $toDelete->delete();

            return [
                'keeper_id'      => $keeperId,
                'deleted_id'     => $toDeleteId,
                'notes_updated'  => $notesUpdated,
                'ventas_updated' => $ventasUpdated,
            ];
        });
    }

    protected function buildPayloadFromLatestUpdated(Customer $source, Customer $keeper, string $searchedPhone): array
    {
        $payload = $source->only([
            'first_names',
            'last_names',
            'phone',
            'secondary_phone',
            'third_phone',
            'phone1_commercial',
            'phone2_commercial',
            'email',
            'nro_piso',
            'postal_code_id',
            'primary_address',
            'secondary_address',
            'parish',
            'dni',
            'fecha_nac',
            'iban',
            'tipo_vivienda',
            'estado_civil',
            'situacion_laboral',
            'ingresos_rango',
            'num_hab_casa',
            'ayuntamiento',
            'edadTelOp',
            'postal_code',
            'ciudad',
            'provincia',
            'antiguedad',
            'nombre_empresa',
            'oficio',
        ]);

        $payload['phone'] = $payload['phone'] ?: $keeper->phone ?: $searchedPhone;

        return $payload;
    }
}