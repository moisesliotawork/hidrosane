<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets;

use App\Services\CustomerDuplicateSearchService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DuplicadosStatsWidget extends StatsOverviewWidget
{
    public bool $duplicatesSearched = false;

    protected function getStats(): array
    {
        if (! $this->duplicatesSearched) {
            return [
                Stat::make('Duplicados', '—')
                    ->description('Pulsa «Buscar duplicados» para escanear la base de datos')
                    ->color('gray')
                    ->icon('heroicon-o-magnifying-glass'),
            ];
        }

        $ids = CustomerDuplicateSearchService::duplicateIdsFromSession();

        if ($ids === []) {
            return [
                Stat::make('Total de registros', 0)
                    ->description('Clientes activos con posible duplicado')
                    ->color('warning')
                    ->icon('heroicon-o-users'),
            ];
        }

        $total = count($ids);

        return [
            Stat::make('Total de registros', $total)
                ->description('Clientes activos con posible duplicado')
                ->color('warning')
                ->icon('heroicon-o-users'),
        ];
    }
}
