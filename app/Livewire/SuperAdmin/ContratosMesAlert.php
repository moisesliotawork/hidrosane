<?php

namespace App\Livewire\SuperAdmin;

use App\Filament\SuperAdmin\Pages\ContratosPorMes;
use App\Support\ContratosPorMesStats;
use Livewire\Component;

class ContratosMesAlert extends Component
{
    public function dismiss(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        ContratosPorMesStats::dismissAlertForUser($user);
    }

    public function render()
    {
        $negatives = ContratosPorMesStats::monthsWithNegativeChanges();
        $visible = $negatives->isNotEmpty()
            && ! ContratosPorMesStats::isAlertDismissedForUser(auth()->user());

        if (! $visible) {
            return view('livewire.superadmin.contratos-mes-alert', [
                'visible' => false,
                'count' => 0,
                'monthsList' => '',
                'url' => ContratosPorMes::getUrl(),
            ]);
        }

        $count = $negatives->count();
        $monthsList = $negatives->map(function ($row) {
            $label = ContratosPorMesStats::labelForMonthKey((string) $row->mes_key);
            $var = (int) data_get($row, 'variacion', 0);

            return "{$label} ({$var})";
        })->implode(' · ');

        return view('livewire.superadmin.contratos-mes-alert', [
            'visible' => true,
            'count' => $count,
            'monthsList' => $monthsList,
            'url' => ContratosPorMes::getUrl(),
        ]);
    }
}
