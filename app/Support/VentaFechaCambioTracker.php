<?php

namespace App\Support;

use App\Models\Venta;
use App\Models\VentaFechaCambio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class VentaFechaCambioTracker
{
    public static function recordIfChanged(Venta $venta, mixed $oldRaw, mixed $newRaw, ?int $userId = null): void
    {
        if (! Schema::hasTable('venta_fecha_cambios')) {
            return;
        }

        $old = self::normalizeDate($oldRaw);
        $new = self::normalizeDate($newRaw);

        if ($old === $new) {
            return;
        }

        $venta->loadMissing('customer');
        $cliente = trim(
            ($venta->customer?->first_names ?? '').' '.($venta->customer?->last_names ?? '')
        );

        $userId ??= auth()->id();
        $userId = $userId !== null ? (int) $userId : null;

        VentaFechaCambio::query()->create([
            'venta_id' => $venta->id,
            'nro_contr_adm' => $venta->nro_contr_adm,
            'cliente_nombre' => $cliente !== '' ? mb_strtoupper($cliente) : null,
            'fecha_anterior' => $old,
            'fecha_nueva' => $new,
            'caused_by_user_id' => $userId,
            'caused_by_label' => ContratosPorMesStats::actorLabelForUserId($userId),
            'ocurrido_at' => now(),
            'acknowledged_at' => null,
        ]);

        ContratoMesBajaTracker::recordFechaChange($venta, $old, $new, $userId);
    }

    public static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, VentaFechaCambio>
     */
    public static function pending(): Collection
    {
        if (! Schema::hasTable('venta_fecha_cambios')) {
            return collect();
        }

        return VentaFechaCambio::query()
            ->pending()
            ->orderByDesc('ocurrido_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public static function pendingCount(): int
    {
        if (! Schema::hasTable('venta_fecha_cambios')) {
            return 0;
        }

        return (int) VentaFechaCambio::query()->pending()->count();
    }

    public static function acknowledgePending(): int
    {
        if (! Schema::hasTable('venta_fecha_cambios')) {
            return 0;
        }

        return VentaFechaCambio::query()
            ->pending()
            ->update(['acknowledged_at' => now()]);
    }

    public static function downloadPendingCsv(): StreamedResponse
    {
        $rows = self::pending();
        $filename = 'cambios-fecha-venta-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                'nro_contr_adm',
                'cliente',
                'fecha_anterior',
                'fecha_nueva',
                'autor',
                'ocurrido_at',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->nro_contr_adm,
                    $row->cliente_nombre,
                    $row->fechaAnteriorLabel(),
                    $row->fechaNuevaLabel(),
                    $row->quienLabel(),
                    $row->ocurrido_at?->format('d/m/Y H:i:s') ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
