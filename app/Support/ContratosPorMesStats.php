<?php

namespace App\Support;

use App\Models\ContratoMesBaseline;
use App\Models\ContratoMesVariacionItem;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

final class ContratosPorMesStats
{
    /**
     * Conteos por mes de contratos ACTIVOS.
     * Soft-deleted no cuentan: al archivar un contrato baja el total y sale VARIACIÓN negativa.
     */
    public static function currentCountsQuery(): Builder
    {
        return Venta::query()
            ->withoutTrashed()
            ->selectRaw("DATE_FORMAT(fecha_venta, '%Y-%m') as mes_key, COUNT(*) as total")
            ->whereNotNull('fecha_venta')
            ->whereNull('ventas.deleted_at')
            ->groupByRaw("DATE_FORMAT(fecha_venta, '%Y-%m')");
    }

    /**
     * Crea baseline = total actual la primera vez que aparece un mes.
     * Así el punto de partida es “sin cambio” hasta que alguien borre/añada.
     */
    public static function ensureBaselines(): void
    {
        $counts = static::currentCountsQuery()
            ->pluck('total', 'mes_key');

        foreach ($counts as $mesKey => $total) {
            ContratoMesBaseline::query()->firstOrCreate(
                ['mes_key' => (string) $mesKey],
                ['baseline_total' => (int) $total],
            );
        }
    }

    /**
     * Fija la base de todos los meses al total actual (tras cambios intencionados).
     */
    public static function freezeBaselinesToCurrent(): int
    {
        $counts = static::currentCountsQuery()
            ->pluck('total', 'mes_key');

        $updated = 0;

        foreach ($counts as $mesKey => $total) {
            ContratoMesBaseline::query()->updateOrCreate(
                ['mes_key' => (string) $mesKey],
                ['baseline_total' => (int) $total],
            );
            $updated++;
        }

        // Meses que quedaron a 0 contratos: baseline también a 0
        $orphanKeys = ContratoMesBaseline::query()
            ->whereNotIn('mes_key', $counts->keys()->all())
            ->pluck('mes_key');

        foreach ($orphanKeys as $mesKey) {
            ContratoMesBaseline::query()
                ->where('mes_key', $mesKey)
                ->update(['baseline_total' => 0]);
            $updated++;
        }

        if (Schema::hasTable('contratos_mes_variacion_items')) {
            ContratoMesVariacionItem::query()->delete();
        }

        return $updated;
    }

    public static function recordVariationItem(Venta $venta, string $estado, ?int $causedByUserId = null): void
    {
        if (! Schema::hasTable('contratos_mes_variacion_items')) {
            return;
        }

        $venta->loadMissing('customer');

        $fecha = $venta->fecha_venta;
        $mesKey = $fecha
            ? Carbon::parse($fecha)->format('Y-m')
            : now()->format('Y-m');

        $cliente = trim(
            ($venta->customer?->first_names ?? '') . ' ' . ($venta->customer?->last_names ?? '')
        );

        // Si se pasa el 3.er argumento (incluso null), se respeta; si no, se infiere.
        $userId = func_num_args() >= 3
            ? $causedByUserId
            : ($venta->deleted_by_user_id ?? auth()->id());

        $label = static::actorLabelForUserId($userId !== null ? (int) $userId : null);

        ContratoMesVariacionItem::query()->updateOrCreate(
            [
                'venta_id' => $venta->id,
                'estado' => $estado,
            ],
            [
                'mes_key' => $mesKey,
                'nro_contr_adm' => $venta->nro_contr_adm,
                'cliente_nombre' => $cliente !== '' ? mb_strtoupper($cliente) : null,
                'dni' => $venta->customer?->dni,
                'ocurrido_at' => now(),
                'caused_by_user_id' => $userId,
                'caused_by_label' => $label,
            ],
        );

        // Si se restaura, quita el soft_delete previo del mismo contrato
        if ($estado === ContratoMesVariacionItem::ESTADO_RESTAURADO) {
            ContratoMesVariacionItem::query()
                ->where('venta_id', $venta->id)
                ->where('estado', ContratoMesVariacionItem::ESTADO_SOFT_DELETE)
                ->delete();
        }

        if ($estado === ContratoMesVariacionItem::ESTADO_SOFT_DELETE) {
            ContratoMesVariacionItem::query()
                ->where('venta_id', $venta->id)
                ->whereIn('estado', [
                    ContratoMesVariacionItem::ESTADO_NUEVO,
                    ContratoMesVariacionItem::ESTADO_RESTAURADO,
                ])
                ->delete();
        }
    }

    public static function actorLabelForUserId(?int $userId): string
    {
        if (! $userId) {
            return 'Sistema / automático';
        }

        $user = \App\Models\User::query()->find($userId);
        if (! $user) {
            return "Usuario #{$userId}";
        }

        if (filled($user->empleado_id)) {
            return trim("{$user->empleado_id} - {$user->name} {$user->last_name}");
        }

        $nombre = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));

        return $nombre !== ''
            ? "{$nombre} (sin ID empleado)"
            : "Usuario #{$user->id}";
    }

    /**
     * @return Collection<int, ContratoMesVariacionItem>
     */
    public static function variationDetailItems(): Collection
    {
        if (! Schema::hasTable('contratos_mes_variacion_items')) {
            return collect();
        }

        return ContratoMesVariacionItem::query()
            ->with([
                'venta' => fn ($q) => $q->withTrashed(),
                'causedBy',
            ])
            ->orderByDesc('ocurrido_at')
            ->orderByDesc('id')
            ->get();
    }

    public static function query(): Builder
    {
        static::ensureBaselines();

        $current = static::currentCountsQuery();

        return ContratoMesBaseline::query()
            ->leftJoinSub($current, 'c', 'c.mes_key', '=', 'contratos_mes_baselines.mes_key')
            ->select([
                'contratos_mes_baselines.mes_key',
                DB::raw('COALESCE(c.total, 0) as total'),
                'contratos_mes_baselines.baseline_total',
                DB::raw('(CAST(COALESCE(c.total, 0) AS SIGNED) - CAST(contratos_mes_baselines.baseline_total AS SIGNED)) as variacion'),
            ])
            ->orderByDesc('contratos_mes_baselines.mes_key');
    }

    /**
     * @return Collection<int, object{mes_key: string, total: int|string, baseline_total: int|string, variacion: int|string}>
     */
    public static function rows(): Collection
    {
        return static::query()->get();
    }

    /**
     * Meses con variación distinta de 0 (para aviso global SuperAdmin).
     *
     * @return Collection<int, object{mes_key: string, total: int|string, baseline_total: int|string, variacion: int|string}>
     */
    public static function monthsWithChanges(): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('contratos_mes_baselines')) {
            return collect();
        }

        try {
            return static::rows()
                ->filter(fn ($row) => (int) data_get($row, 'variacion', 0) !== 0)
                ->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Solo meses con VARIACIÓN negativa (menos contratos que la base).
     *
     * @return Collection<int, object{mes_key: string, total: int|string, baseline_total: int|string, variacion: int|string}>
     */
    public static function monthsWithNegativeChanges(): Collection
    {
        return static::monthsWithChanges()
            ->filter(fn ($row) => (int) data_get($row, 'variacion', 0) < 0)
            ->values();
    }

    public static function hasChanges(): bool
    {
        return static::monthsWithChanges()->isNotEmpty();
    }

    public static function hasNegativeChanges(): bool
    {
        return static::monthsWithNegativeChanges()->isNotEmpty();
    }

    /**
     * Firma del estado actual de meses negativos (para descartar por usuario).
     */
    public static function negativeChangesFingerprint(): string
    {
        $parts = static::monthsWithNegativeChanges()
            ->map(fn ($row) => (string) $row->mes_key . ':' . (int) data_get($row, 'variacion', 0))
            ->sort()
            ->values()
            ->all();

        return hash('sha256', implode('|', $parts));
    }

    public static function isAlertDismissedForUser(?\App\Models\User $user): bool
    {
        if (! $user) {
            return true;
        }

        if (! static::hasNegativeChanges()) {
            return true;
        }

        $stored = \App\Models\AppSetting::get(static::alertDismissSettingKey($user->id));
        if (! is_array($stored)) {
            return false;
        }

        return ($stored['fingerprint'] ?? null) === static::negativeChangesFingerprint();
    }

    public static function dismissAlertForUser(\App\Models\User $user): void
    {
        if (! static::hasNegativeChanges()) {
            return;
        }

        \App\Models\AppSetting::set(static::alertDismissSettingKey($user->id), [
            'fingerprint' => static::negativeChangesFingerprint(),
            'dismissed_at' => now()->toIso8601String(),
        ]);
    }

    public static function alertDismissSettingKey(int $userId): string
    {
        return "contratos_mes_alert_dismiss.{$userId}";
    }

    public static function labelForMonthKey(string $mesKey): string
    {
        try {
            return ucfirst(
                Carbon::createFromFormat('Y-m', $mesKey)
                    ->locale('es')
                    ->translatedFormat('F Y')
            );
        } catch (\Throwable) {
            return $mesKey;
        }
    }

    /**
     * Contratos activos con ID registro + nº admin, agrupados por mes de fecha_venta.
     *
     * @return Collection<int, object{mes_key: string, contratos: list<object{id: int, nro_contr_adm: string}>, total: int}>
     */
    public static function adminContractNumbersByMonth(?string $mesKey = null): Collection
    {
        $query = Venta::query()
            ->withoutTrashed()
            ->whereNotNull('fecha_venta')
            ->whereNull('ventas.deleted_at')
            ->whereNotNull('nro_contr_adm')
            ->where('nro_contr_adm', '!=', '')
            ->select([
                'id',
                'nro_contr_adm',
                DB::raw("DATE_FORMAT(fecha_venta, '%Y-%m') as mes_key"),
            ])
            ->orderByRaw("DATE_FORMAT(fecha_venta, '%Y-%m') desc")
            ->orderBy('id');

        if (filled($mesKey)) {
            $query->whereRaw("DATE_FORMAT(fecha_venta, '%Y-%m') = ?", [$mesKey]);
        }

        return $query->get()
            ->groupBy('mes_key')
            ->map(function (Collection $group, string $key) {
                $contratos = $group
                    ->map(fn ($row) => (object) [
                        'id' => (int) $row->id,
                        'nro_contr_adm' => trim((string) $row->nro_contr_adm),
                    ])
                    ->filter(fn ($c) => $c->nro_contr_adm !== '')
                    ->values()
                    ->all();

                return (object) [
                    'mes_key' => $key,
                    'contratos' => $contratos,
                    'total' => count($contratos),
                ];
            })
            ->sortByDesc(fn ($row) => $row->mes_key)
            ->values();
    }

    /**
     * Solo nro_contr_adm activos (lista plana, ordenados).
     *
     * @return list<string>
     */
    public static function adminContractNumbersOnly(?string $mesKey = null): array
    {
        $query = Venta::query()
            ->withoutTrashed()
            ->whereNotNull('fecha_venta')
            ->whereNull('ventas.deleted_at')
            ->whereNotNull('nro_contr_adm')
            ->where('nro_contr_adm', '!=', '')
            ->orderBy('nro_contr_adm');

        if (filled($mesKey)) {
            $query->whereRaw("DATE_FORMAT(fecha_venta, '%Y-%m') = ?", [$mesKey]);
        }

        return $query
            ->pluck('nro_contr_adm')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function hayCambioHtml(int $variacion): HtmlString
    {
        if ($variacion === 0) {
            return new HtmlString(
                '<span class="contratos-mes-var contratos-mes-var-same">NO</span>'
            );
        }

        $colorClass = $variacion < 0 ? 'contratos-mes-var-down' : 'contratos-mes-var-up';

        return new HtmlString(
            '<span class="contratos-mes-var ' . $colorClass . ' contratos-mes-blink">SÍ</span>'
        );
    }

    public static function variacionHtml(int $variacion): HtmlString
    {
        if ($variacion === 0) {
            return new HtmlString(
                '<span class="contratos-mes-var contratos-mes-var-same">0</span>'
            );
        }

        if ($variacion < 0) {
            return new HtmlString(
                '<span class="contratos-mes-var contratos-mes-var-down contratos-mes-blink">'
                . e((string) $variacion)
                . '</span>'
            );
        }

        return new HtmlString(
            '<span class="contratos-mes-var contratos-mes-var-up contratos-mes-blink">+'
            . e((string) $variacion)
            . '</span>'
        );
    }
}
