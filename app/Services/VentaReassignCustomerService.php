<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Venta;
use App\Support\PuertaFriaCustomerSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaReassignCustomerService
{
    public static function normalizeDni(?string $dni): string
    {
        return mb_strtoupper(trim((string) $dni));
    }

    /**
     * Busca clientes activos por nombre y/o DNI.
     *
     * @return Collection<int, array{
     *     id: int,
     *     label: string,
     *     name: string,
     *     nro_cliente: string,
     *     first_names: string,
     *     last_names: string,
     *     dni: string,
     *     phone: string,
     *     address: string,
     *     contratos: int
     * }>
     */
    public function search(?string $name, ?string $dni): Collection
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
        $dniNorm = static::normalizeDni($dni);

        if ($name === '' && $dniNorm === '') {
            return collect();
        }

        $byId = collect();

        if ($dniNorm !== '') {
            Customer::query()
                ->whereRaw('UPPER(TRIM(COALESCE(dni, ""))) = ?', [$dniNorm])
                ->get()
                ->each(fn (Customer $c) => $byId->put($c->id, $c));
        }

        if ($name !== '') {
            PuertaFriaCustomerSearch::resolveCustomersForName($name)
                ->each(fn (Customer $c) => $byId->put($c->id, $c));
        }

        return $byId->values()->map(function (Customer $customer): array {
            $contratos = Venta::query()
                ->where('customer_id', $customer->id)
                ->whereNull('deleted_at')
                ->count();

            $full = trim($customer->first_names.' '.$customer->last_names);
            $dni = (string) ($customer->dni ?? '—');
            // Nº que asigna Admin en contratos (ventas.nro_cliente_adm), no el id del sistema.
            $nroCliente = $customer->firstVentaClienteAdmin();
            if ($nroCliente === '' || $nroCliente === null) {
                $nroCliente = '-';
            }
            $phones = collect([
                $customer->phone,
                $customer->secondary_phone,
                $customer->third_phone,
            ])->filter(fn ($p) => filled($p))->map(fn ($p) => (string) $p)->values();
            $phone = $phones->isNotEmpty() ? $phones->implode(' / ') : '—';
            $addressParts = collect([
                $customer->primary_address,
                $customer->nro_piso,
                $customer->postal_code,
                $customer->ciudad,
            ])->filter(fn ($p) => filled($p))->map(fn ($p) => trim((string) $p));
            $address = $addressParts->isNotEmpty() ? $addressParts->implode(', ') : '—';

            return [
                'id' => (int) $customer->id,
                'label' => "Nº Cliente {$nroCliente} · {$full} · DNI {$dni} · ID/Sist {$customer->id}",
                'name' => $full,
                'nro_cliente' => $nroCliente,
                'first_names' => (string) $customer->first_names,
                'last_names' => (string) $customer->last_names,
                'dni' => $dni,
                'phone' => $phone,
                'address' => $address,
                'contratos' => $contratos,
            ];
        })->values();
    }

    public function findByDni(string $dni): ?Customer
    {
        $dniNorm = static::normalizeDni($dni);
        if ($dniNorm === '') {
            return null;
        }

        return Customer::query()
            ->whereRaw('UPPER(TRIM(COALESCE(dni, ""))) = ?', [$dniNorm])
            ->first();
    }

    /**
     * Reasigna SOLO este contrato (y sus -B asociados vía sync) a un cliente existente.
     */
    public function assignToExisting(Venta $venta, int $customerId): Customer
    {
        return DB::transaction(function () use ($venta, $customerId): Customer {
            $customer = Customer::query()->findOrFail($customerId);

            if ((int) $venta->customer_id === (int) $customer->id) {
                return $customer;
            }

            $venta->customer_id = $customer->id;
            $venta->save();

            VentaNotesCustomerSync::syncFromVenta($venta->fresh(['customer', 'note', 'asociadas', 'asociadasConmigo']));

            return $customer;
        });
    }

    /**
     * Crea cliente nuevo y asigna solo este contrato.
     * Si el DNI ya existe, lanza validación (hay que buscar, no duplicar).
     */
    public function createAndAssign(Venta $venta, string $firstNames, string $lastNames, string $dni): Customer
    {
        $firstNames = trim($firstNames);
        $lastNames = trim($lastNames);
        $dniNorm = static::normalizeDni($dni);

        if ($firstNames === '' || $lastNames === '' || $dniNorm === '') {
            throw ValidationException::withMessages([
                'dni' => 'Nombre, apellidos y DNI son obligatorios.',
            ]);
        }

        if ($existing = $this->findByDni($dniNorm)) {
            throw ValidationException::withMessages([
                'dni' => "Ya existe un cliente con DNI {$dniNorm} (ID {$existing->id}). Usa «Buscar si es cliente».",
            ]);
        }

        return DB::transaction(function () use ($venta, $firstNames, $lastNames, $dniNorm): Customer {
            $venta->loadMissing('customer');
            $payload = [
                'first_names' => $firstNames,
                'last_names' => $lastNames,
                'dni' => $dniNorm,
                'primary_address' => (string) ($venta->customer?->primary_address ?: '—'),
            ];

            // Copia datos no-identidad del cliente actual (teléfono, dirección, etc.)
            // para no dejar la ficha nueva vacía / romper NOT NULL.
            if ($venta->customer) {
                $copyFields = [
                    'phone', 'secondary_phone', 'third_phone',
                    'phone1_commercial', 'phone2_commercial',
                    'email', 'nro_piso', 'postal_code_id', 'postal_code',
                    'primary_address', 'secondary_address', 'parish',
                    'ciudad', 'provincia', 'ayuntamiento',
                    'fecha_nac', 'iban', 'tipo_vivienda', 'estado_civil',
                    'situacion_laboral', 'ingresos_rango', 'num_hab_casa',
                    'edadTelOp', 'antiguedad', 'nombre_empresa', 'oficio',
                ];

                foreach ($copyFields as $field) {
                    $value = $venta->customer->{$field};
                    if ($value !== null && $value !== '') {
                        $payload[$field] = $value;
                    }
                }
            }

            $customer = Customer::query()->create($payload);

            $venta->customer_id = $customer->id;
            $venta->save();

            VentaNotesCustomerSync::syncFromVenta($venta->fresh(['customer', 'note', 'asociadas', 'asociadasConmigo']));

            return $customer;
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitFullName(string $fullName): array
    {
        $parts = PuertaFriaCustomerSearch::splitLookupName($fullName);

        return [
            (string) ($parts['first_names'] ?? ''),
            (string) ($parts['last_names'] ?? ''),
        ];
    }
}
