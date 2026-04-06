<?php

namespace App\Services;

use App\Models\Customer;
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