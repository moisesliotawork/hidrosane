<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoEntrega;
use App\Enums\EstadoReparto;
use App\Models\Reparto;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class RepartidorStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected static ?int $sort = 1; // opcional: orden en el panel

    protected function getStats(): array
    {
        $userId = auth()->id();

        $now = Carbon::now();
        $mesInicio = $now->copy()->startOfMonth();
        $mesFin = $now->copy()->endOfMonth();

        $mesPasadoInicio = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $mesPasadoFin = $now->copy()->subMonthNoOverflow()->endOfMonth();

        // Helper base query para filtrar por repartidor en sesión
        $base = fn() => Reparto::query()
            ->whereHas('venta', fn($q) => $q->where('repartidor_id', $userId));

        // === Repartos COMPLETADOS por mes (usando updated_at) ===
        $repartosMes = (clone $base())
            ->where('estado_entrega', EstadoEntrega::COMPLETO->value)
            ->whereBetween('updated_at', [$mesInicio, $mesFin])
            ->count();

        $repartosMesPasado = (clone $base())
            ->where('estado_entrega', EstadoEntrega::COMPLETO->value)
            ->whereBetween('updated_at', [$mesPasadoInicio, $mesPasadoFin])
            ->count();

        // === Repartos con VENTA (estado = entrega_venta) por mes (updated_at) ===
        $ventasMes = (clone $base())
            ->where('estado', EstadoReparto::ENTREGA_VENTA->value)
            ->where('estado_entrega', EstadoEntrega::COMPLETO->value)
            ->whereBetween('updated_at', [$mesInicio, $mesFin])
            ->count();

        $ventasMesPasado = (clone $base())
            ->where('estado', EstadoReparto::ENTREGA_VENTA->value)
            ->where('estado_entrega', EstadoEntrega::COMPLETO->value)
            ->whereBetween('updated_at', [$mesPasadoInicio, $mesPasadoFin])
            ->count();

        return [
            Stat::make('Repartos completados (mes)', number_format($repartosMes))
                ->description($mesInicio->format('d/m') . ' - ' . $mesFin->format('d/m'))
                ->descriptionIcon('heroicon-o-truck')
                ->color('success'),

            Stat::make('Repartos completados (mes pasado)', number_format($repartosMesPasado))
                ->description($mesPasadoInicio->format('d/m') . ' - ' . $mesPasadoFin->format('d/m'))
                ->descriptionIcon('heroicon-o-arrow-uturn-left')
                ->color('success'),

            Stat::make('Repartos con venta (mes)', number_format($ventasMes))
                ->description($mesInicio->format('d/m') . ' - ' . $mesFin->format('d/m'))
                ->descriptionIcon('heroicon-o-currency-euro')
                ->color('primary'),

            Stat::make('Repartos con venta (mes pasado)', number_format($ventasMesPasado))
                ->description($mesPasadoInicio->format('d/m') . ' - ' . $mesPasadoFin->format('d/m'))
                ->descriptionIcon('heroicon-o-arrow-uturn-left')
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 2; // 2x2
    }
}
