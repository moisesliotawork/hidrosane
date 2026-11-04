<?php

namespace App\Support;

use App\Enums\EstadoTerminal;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Soft-block: notas abiertas del mismo cliente (teléfonos / duplicados)
 * dejan de verse en agenda comercial cuando aparece una venta reciente.
 * No cambian de estado; se pueden reactivar forzando asignación en JE.
 */
class RecentSaleAgendaBlocker
{
    public static function recentSaleCutoff(): Carbon
    {
        return now()->startOfMonth()->subMonthsNoOverflow(4);
    }

    /**
     * Marca notas abiertas vinculadas por teléfono a la venta recién creada.
     */
    public static function blockOpenNotesForVenta(Venta $venta): int
    {
        if (! $venta->id) {
            return 0;
        }

        $customerIds = self::relatedCustomerIdsForVenta($venta);

        if ($customerIds->isEmpty()) {
            return 0;
        }

        return Note::query()
            ->whereIn('customer_id', $customerIds->all())
            ->when(
                $venta->note_id,
                fn ($q) => $q->where('id', '!=', $venta->note_id)
            )
            ->whereDoesntHave('venta')
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhereNotIn('estado_terminal', [
                        EstadoTerminal::VENTA->value,
                        EstadoTerminal::NUL->value,
                    ]);
            })
            ->whereNull('blocked_by_venta_id')
            ->update(['blocked_by_venta_id' => $venta->id]);
    }

    /**
     * Limpia el soft-block (p. ej. al forzar asignación en Jefe de Sala).
     */
    public static function clearBlock(Note $note): void
    {
        if ($note->blocked_by_venta_id === null) {
            return;
        }

        $note->forceFill(['blocked_by_venta_id' => null])->saveQuietly();
    }

    public static function clearBlocksForVenta(Venta $venta): int
    {
        if (! $venta->id) {
            return 0;
        }

        return Note::query()
            ->where('blocked_by_venta_id', $venta->id)
            ->update(['blocked_by_venta_id' => null]);
    }

    public static function isBlocked(Note $note): bool
    {
        return $note->blocked_by_venta_id !== null;
    }

    /**
     * Motivo para impedir gestionar.
     * Solo Jefe de Sala / Admin / SuperAdmin pueden entrar a decidir.
     */
    public static function manageBlockReason(Note $note): ?string
    {
        if (! self::isBlocked($note)) {
            return null;
        }

        $user = auth()->user();

        if ($user?->hasAnyRole([
            'head_of_room',
            'admin',
            'super_admin',
        ])) {
            return null;
        }

        $venta = $note->relationLoaded('blockedByVenta')
            ? $note->blockedByVenta
            : Venta::query()
                ->with('comercial:id,empleado_id,name')
                ->find($note->blocked_by_venta_id);

        if (! $venta) {
            return 'Esta nota está bloqueada por una venta reciente del mismo cliente. No se puede gestionar desde agenda.';
        }

        $fecha = $venta->fecha_venta
            ? Carbon::parse($venta->fecha_venta)->format('d/m/Y H:i')
            : '—';
        $emp = $venta->comercial?->empleado_id ?? '—';
        $contrato = $venta->nro_contr_adm ?: $venta->id;

        return "Nota bloqueada por venta reciente (contrato {$contrato}, {$fecha}, comercial {$emp}). Consulta con Jefe de Sala o Admin.";
    }

    public static function viewerCanSeeBlockedNotes(?\App\Models\User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) $user?->hasAnyRole([
            'head_of_room',
            'admin',
            'super_admin',
        ]);
    }

    /**
     * @return Collection<int, int>
     */
    public static function relatedCustomerIdsForVenta(Venta $venta): Collection
    {
        $customer = $venta->relationLoaded('customer')
            ? $venta->customer
            : Customer::query()->find($venta->customer_id);

        if (! $customer) {
            return collect();
        }

        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $phones = $guard->phonesFromCustomer($customer);
        $related = $phones === []
            ? collect([$customer])
            : $guard->resolveCustomersForPhones($phones);

        if ($related->isEmpty()) {
            $related = collect([$customer]);
        }

        return $related->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * Notas soft-bloqueadas visibles para avisar al comercial / JE.
     *
     * @param  array<int>  $comercialIds
     * @return list<array{
     *     note_id: int,
     *     nro_nota: string|int|null,
     *     cliente: string,
     *     assignment_date: ?string,
     *     contrato: string,
     *     fecha_venta: string,
     *     comercial_venta: string
     * }>
     */
    public static function summarizeBlockedForComercialIds(array $comercialIds, bool $retenOnly = false): array
    {
        $comercialIds = array_values(array_filter(array_map('intval', $comercialIds)));

        if ($comercialIds === [] && ! $retenOnly) {
            return [];
        }

        $query = Note::query()
            ->with([
                'customer:id,first_names,last_names',
                'blockedByVenta:id,nro_contr_adm,fecha_venta,comercial_id',
                'blockedByVenta.comercial:id,empleado_id,name',
            ])
            ->whereNotNull('blocked_by_venta_id')
            ->whereDoesntHave('venta');

        if ($retenOnly) {
            $query->where('reten', true);
        } else {
            $query->whereIn('comercial_id', $comercialIds)
                ->where(function ($q) {
                    $q->whereNull('reten')->orWhere('reten', false);
                });
        }

        return $query
            ->orderByDesc('assignment_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (Note $note) {
                $venta = $note->blockedByVenta;
                $customer = $note->customer;

                return [
                    'note_id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'cliente' => trim(($customer->first_names ?? '').' '.($customer->last_names ?? '')),
                    'assignment_date' => optional($note->assignment_date)?->format('d/m/Y'),
                    'contrato' => (string) ($venta?->nro_contr_adm ?: ($venta?->id ?? '—')),
                    'fecha_venta' => $venta?->fecha_venta
                        ? Carbon::parse($venta->fecha_venta)->format('d/m/Y H:i')
                        : '—',
                    'comercial_venta' => (string) ($venta?->comercial?->empleado_id ?? '—'),
                ];
            })
            ->all();
    }

    /**
     * Backfill: ventas recientes → bloquea notas abiertas hermanas (mismo teléfono).
     */
    public static function backfillFromRecentVentas(?Carbon $cutoff = null): int
    {
        $cutoff ??= self::recentSaleCutoff();
        $total = 0;

        Venta::query()
            ->with('customer')
            ->where('fecha_venta', '>=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($ventas) use (&$total) {
                foreach ($ventas as $venta) {
                    $total += self::blockOpenNotesForVenta($venta);
                }
            });

        return $total;
    }
}
