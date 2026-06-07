<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DuplicadosStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $phoneFields = ['phone', 'secondary_phone', 'third_phone', 'phone1_commercial', 'phone2_commercial'];

        $baseQuery = Customer::query()
            ->whereIn('id', function ($sub) use ($phoneFields) {
                $sub->select('c1.id')
                    ->from('customers as c1')
                    ->join('customers as c2', 'c1.id', '!=', 'c2.id')
                    ->whereNull('c1.merged_into_id')
                    ->whereNull('c2.merged_into_id')
                    ->whereRaw(
                        "TRIM(CONCAT(COALESCE(c1.first_names,''),' ',COALESCE(c1.last_names,''))) "
                        . "= TRIM(CONCAT(COALESCE(c2.first_names,''),' ',COALESCE(c2.last_names,'')))"
                    )
                    ->whereRaw('c1.dni = c2.dni')
                    ->whereRaw("c1.dni IS NOT NULL AND c1.dni != ''")
                    ->where(function ($q) use ($phoneFields) {
                        foreach ($phoneFields as $f1) {
                            foreach ($phoneFields as $f2) {
                                $q->orWhereRaw(
                                    "(c1.`{$f1}` IS NOT NULL AND c1.`{$f1}` != '' AND c1.`{$f1}` = c2.`{$f2}`)"
                                );
                            }
                        }
                    });
            });

        $total = (clone $baseQuery)->count();

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
