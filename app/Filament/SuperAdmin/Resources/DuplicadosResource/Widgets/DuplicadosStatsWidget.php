<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets;

use App\Models\CustomerAutoMergeRun;
use App\Services\CustomerDuplicateSearchService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class DuplicadosStatsWidget extends StatsOverviewWidget
{
    public bool $duplicatesSearched = false;

    protected function getStats(): array
    {
        return [
            $this->duplicadosStat(),
            $this->ultimaDuplicacionStat(),
        ];
    }

    protected function duplicadosStat(): Stat
    {
        if (! $this->duplicatesSearched) {
            return Stat::make('Duplicados', '—')
                ->description('Pulsa «Buscar duplicados» para escanear la base de datos')
                ->color('gray')
                ->icon('heroicon-o-magnifying-glass');
        }

        $ids = CustomerDuplicateSearchService::duplicateIdsFromSession();
        $total = count($ids);

        return Stat::make('Duplicados', $total)
            ->description('Clientes activos con posible duplicado')
            ->color($total > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-users');
    }

    protected function ultimaDuplicacionStat(): Stat
    {
        if (! Schema::hasTable('customer_auto_merge_runs')) {
            return Stat::make('Última duplicación', '—')
                ->description('Tabla de historial no disponible (ejecutar migraciones)')
                ->color('gray')
                ->icon('heroicon-o-arrows-right-left');
        }

        $lastRun = CustomerAutoMergeRun::latestRun();

        if ($lastRun === null) {
            return Stat::make('Última duplicación', '—')
                ->description('Aún no se ha ejecutado ninguna fusión automática')
                ->color('gray')
                ->icon('heroicon-o-arrows-right-left');
        }

        $dateLabel = $lastRun->ran_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? '—';
        $description = 'Ejecutada el ' . $dateLabel;

        if ($lastRun->failed_count > 0) {
            $description .= " · {$lastRun->failed_count} error(es)";
        }

        return Stat::make('Última duplicación', $lastRun->merged_count)
            ->description($description)
            ->color($lastRun->failed_count > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-arrows-right-left');
    }
}
