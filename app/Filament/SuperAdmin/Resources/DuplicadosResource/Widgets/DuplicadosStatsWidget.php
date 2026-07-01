<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets;

use App\Models\Customer;
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

        $baseQuery = Customer::query()->whereIn('id', $ids);

        $total = count($ids);

        /** @var Customer|null $latest */
        $latest = (clone $baseQuery)->latest('created_at')->first();

        $lastLabel = '—';
        $lastDate  = '—';

        if ($latest) {
            $lastLabel = mb_strtoupper(trim($latest->first_names . ' ' . $latest->last_names));
            $lastDate  = optional($latest->created_at)->format('d/m/Y H:i') ?? '—';
        }

        return [
            Stat::make('Total de registros', $total)
                ->description('Clientes activos con posible duplicado')
                ->color('warning')
                ->icon('heroicon-o-users'),

            Stat::make('Última duplicación', $lastLabel)
                ->description('Creado el: ' . $lastDate)
                ->color('danger')
                ->icon('heroicon-o-clock'),
        ];
    }
}
