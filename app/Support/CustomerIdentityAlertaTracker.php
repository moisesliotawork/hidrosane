<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerIdentityAlerta;
use App\Models\Venta;
use App\Services\VentaCustomerIdentityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class CustomerIdentityAlertaTracker
{
    public static function enabled(): bool
    {
        return Schema::hasTable('customer_identity_alertas');
    }

    /**
     * @param  array<string, mixed>  $newData
     */
    public static function recordIfMatches(Venta $venta, Customer $original, array $newData): void
    {
        if (! self::enabled() || ! VentaCustomerIdentityService::identityChanged($original, $newData)) {
            return;
        }

        $despues = [
            'first_names' => array_key_exists('first_names', $newData) ? $newData['first_names'] : $original->first_names,
            'last_names' => array_key_exists('last_names', $newData) ? $newData['last_names'] : $original->last_names,
            'dni' => array_key_exists('dni', $newData) ? $newData['dni'] : $original->dni,
        ];

        $dniNuevo = CustomerIdentityMatch::dni($despues['dni']);
        $nombreNuevo = CustomerIdentityMatch::nombreCompleto($despues['first_names'], $despues['last_names']);
        if ($dniNuevo === '' && $nombreNuevo === '') {
            return;
        }

        $otros = Customer::query()
            ->whereKeyNot($original->id)
            ->where(function ($query) use ($dniNuevo, $despues, $nombreNuevo): void {
                if ($dniNuevo !== '') {
                    $query->orWhereRaw('UPPER(TRIM(dni)) = ?', [$dniNuevo]);
                }

                if ($nombreNuevo !== '') {
                    $query->orWhere(function ($name) use ($despues): void {
                        $name->whereRaw('LOWER(TRIM(first_names)) = ?', [mb_strtolower(trim((string) $despues['first_names']))])
                            ->whereRaw('LOWER(TRIM(last_names)) = ?', [mb_strtolower(trim((string) $despues['last_names']))]);
                    });
                }
            })
            ->limit(15)
            ->get(['id', 'first_names', 'last_names', 'dni'])
            ->map(fn (Customer $customer): array => [
                'id' => (int) $customer->id,
                'first_names' => $customer->first_names,
                'last_names' => $customer->last_names,
                'dni' => $customer->dni,
            ])
            ->all();

        $coincidencias = CustomerIdentityMatch::buscar($despues, $otros, (int) $original->id);
        if ($coincidencias === []) {
            return;
        }

        $userId = auth()->id();
        $userId = $userId !== null ? (int) $userId : null;
        $nombreAntes = trim((string) $original->first_names.' '.(string) $original->last_names);
        $nombreDespues = trim((string) $despues['first_names'].' '.(string) $despues['last_names']);

        foreach ($coincidencias as $coincidencia) {
            CustomerIdentityAlerta::query()->create([
                'venta_id' => $venta->id,
                'nro_contr_adm' => $venta->nro_contr_adm,
                'customer_id' => $original->id,
                'coincidencia_customer_id' => $coincidencia['id'],
                'nombre_antes' => $nombreAntes !== '' ? $nombreAntes : null,
                'dni_antes' => filled($original->dni) ? (string) $original->dni : null,
                'nombre_despues' => $nombreDespues !== '' ? $nombreDespues : null,
                'dni_despues' => filled($despues['dni']) ? (string) $despues['dni'] : null,
                'coincidencia_nombre' => $coincidencia['nombre'] !== '' ? $coincidencia['nombre'] : null,
                'coincidencia_dni' => $coincidencia['dni'] !== '' ? $coincidencia['dni'] : null,
                'via' => $coincidencia['via'],
                'caused_by_user_id' => $userId,
                'caused_by_label' => ContratosPorMesStats::actorLabelForUserId($userId),
                'ocurrido_at' => now(),
                'acknowledged_at' => null,
            ]);
        }
    }

    /**
     * @return Collection<int, CustomerIdentityAlerta>
     */
    public static function pending(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return CustomerIdentityAlerta::query()
            ->pending()
            ->orderByDesc('ocurrido_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get();
    }

    public static function pendingCount(): int
    {
        if (! self::enabled()) {
            return 0;
        }

        return (int) CustomerIdentityAlerta::query()->pending()->count();
    }

    public static function acknowledgePending(): int
    {
        if (! self::enabled()) {
            return 0;
        }

        return CustomerIdentityAlerta::query()
            ->pending()
            ->update(['acknowledged_at' => now()]);
    }
}
