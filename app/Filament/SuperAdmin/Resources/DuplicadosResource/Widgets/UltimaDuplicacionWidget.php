<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets;

use App\Models\CustomerAutoMergeRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UltimaDuplicacionWidget extends StatsOverviewWidget
{
    protected static ?string $heading = 'Última duplicación';

    protected function getStats(): array
    {
        $lastRun = CustomerAutoMergeRun::latestRun();

        if ($lastRun === null) {
            return [
                Stat::make('Clientes fusionados', '—')
                    ->description('Aún no se ha ejecutado ninguna fusión automática')
                    ->color('gray')
                    ->icon('heroicon-o-arrows-right-left'),
            ];
        }

        $dateLabel = $lastRun->ran_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? '—';

        $description = 'Ejecutada el ' . $dateLabel;

        if ($lastRun->failed_count > 0) {
            $description .= " · {$lastRun->failed_count} error(es)";
        }

        return [
            Stat::make('Clientes fusionados', $lastRun->merged_count)
                ->description($description)
                ->color($lastRun->failed_count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-arrows-right-left'),
        ];
    }
}
