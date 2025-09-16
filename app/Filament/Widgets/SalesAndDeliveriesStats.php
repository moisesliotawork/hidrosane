<?php

namespace App\Filament\Widgets;

use App\Models\Venta;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class SalesAndDeliveriesStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected function ventasUrl(): ?string
    {
        $user = Auth::user();
        if (!$user)
            return null;

        // Ajusta los FQCN si tus resources están en otro namespace
        if ($user->hasRole('admin')) {
            return \App\Filament\Admin\Resources\VentaResource::getUrl(); // index del resource en Admin
        }

        if ($user->hasRole('gerente_general')) {
            return \App\Filament\Gerente\Resources\VentaResource::getUrl(); // index del resource en Gerente
        }

        return null; // sin enlace para otros roles
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $now = Carbon::now();

        $ttl = now()->addMinutes(5);

        /** ====== DÍA ====== */
        $ventasHoy = Cache::remember(
            'ventas_hoy',
            $ttl,
            fn() => Venta::whereDate('fecha_venta', $today)->count()
        );
        $ventasAyer = Cache::remember(
            'ventas_ayer',
            $ttl,
            fn() => Venta::whereDate('fecha_venta', $yesterday)->count()
        );
        $repartosHoy = Cache::remember(
            'repartos_hoy',
            $ttl,
            fn() => Venta::whereDate('fecha_entrega', $today)->count()
        );
        $repartosAyer = Cache::remember(
            'repartos_ayer',
            $ttl,
            fn() => Venta::whereDate('fecha_entrega', $yesterday)->count()
        );

        /** ====== MES ====== */
        $mStart = $now->copy()->startOfMonth();
        $mEnd = $now->copy()->endOfMonth();
        $pmStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $pmEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $ventasMes = Cache::remember(
            'ventas_mes',
            $ttl,
            fn() => Venta::whereBetween('fecha_venta', [$mStart, $mEnd])->count()
        );
        $ventasMesAnterior = Cache::remember(
            'ventas_mes_ant',
            $ttl,
            fn() => Venta::whereBetween('fecha_venta', [$pmStart, $pmEnd])->count()
        );

        /** ====== SEMANA ====== */
        $wStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $wEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);
        $pwStart = $wStart->copy()->subWeek();
        $pwEnd = $wEnd->copy()->subWeek();

        $ventasSemana = Cache::remember(
            'ventas_semana',
            $ttl,
            fn() => Venta::whereBetween('fecha_venta', [$wStart, $wEnd])->count()
        );
        $ventasSemanaAnterior = Cache::remember(
            'ventas_semana_ant',
            $ttl,
            fn() => Venta::whereBetween('fecha_venta', [$pwStart, $pwEnd])->count()
        );
        $repartosSemana = Cache::remember(
            'repartos_semana',
            $ttl,
            fn() => Venta::whereBetween('fecha_entrega', [$wStart, $wEnd])->count()
        );
        $repartosSemanaAnterior = Cache::remember(
            'repartos_semana_ant',
            $ttl,
            fn() => Venta::whereBetween('fecha_entrega', [$pwStart, $pwEnd])->count()
        );

        $ventasUrl = $this->ventasUrl();

        return [
            // === DÍA ===
            Stat::make('VENTAS AYER', number_format($ventasAyer))
                ->description('Total Ventas AYER')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('success')
                ->url($ventasUrl),

            Stat::make('VENTAS HOY', number_format($ventasHoy))
                ->description('Total Ventas HOY')
                ->descriptionIcon('heroicon-o-sparkles')
                ->color('success')
                ->url($ventasUrl),

            Stat::make('VENTAS SEMANA ANTERIOR', number_format($ventasSemanaAnterior))
                ->description('Total Ventas SEMANA ANTERIOR')
                ->descriptionIcon('heroicon-o-arrow-left')
                ->color('success'),

            Stat::make('VENTAS SEMANA', number_format($ventasSemana))
                ->description('Total Ventas SEMANA ACTUAL')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('success'),

            Stat::make('VENTAS MES ANTERIOR', number_format($ventasMesAnterior))
                ->description('Total Ventas MES ANTERIOR')
                ->descriptionIcon('heroicon-o-arrow-uturn-left')
                ->color('success'),

            Stat::make('VENTAS MES', number_format($ventasMes))
                ->description('Total Ventas MES ACTUAL')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('success'),


            Stat::make('REPARTOS AYER', number_format($repartosAyer))
                ->description('Total Entregas AYER')
                ->descriptionIcon('heroicon-o-truck')
                ->color('orange'),

            Stat::make('REPARTOS HOY', number_format($repartosHoy))
                ->description('Total Entregas HOY')
                ->descriptionIcon('heroicon-o-truck')
                ->color('orange'),

            Stat::make('REPARTO SEMANA ANTERIOR', number_format($repartosSemanaAnterior))
                ->description('Total Entregas SEMANA ANTERIOR')
                ->descriptionIcon('heroicon-o-truck')
                ->color('orange'),

            Stat::make('REPARTO SEMANA', number_format($repartosSemana))
                ->description('Total Entregas SEMANA ACTUAL')
                ->descriptionIcon('heroicon-o-truck')
                ->color('orange'),

        ];
    }

    protected function getColumns(): int
    {
        return 2; // Dos columnas en móvil, se adapta en pantallas grandes
    }
}
