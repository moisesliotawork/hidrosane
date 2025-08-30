<?php

namespace App\Filament\Gerente\Resources\TeleoperadoraResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\Gerente\Resources\TeleoperadoraResource;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTeleoperadoras extends ListRecords
{
    protected static string $resource = TeleoperadoraResource::class;

    public function getTabs(): array
    {
        $applyCounts = function (Builder $query, Carbon $start, Carbon $end): Builder {
            return $query->withCount([
                // CONFIRMADAS = CONFIRMADO  (antes tenías SALA)
                'notes as confirmadas_count' => function ($n) use ($start, $end) {
                    $n->whereBetween('created_at', [$start, $end])
                        ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value);
                },
                // VENTAS = VENTA
                'notes as vendidas_count' => function ($n) use ($start, $end) {
                    $n->whereBetween('created_at', [$start, $end])
                        ->where('estado_terminal', EstadoTerminal::VENTA->value);
                },
            ]);
        };

        $currStart = now()->startOfMonth();
        $currEnd = now()->endOfMonth();
        $prevStart = now()->subMonth()->startOfMonth();
        $prevEnd = now()->subMonth()->endOfMonth();
        $prev2Start = now()->subMonths(2)->startOfMonth();
        $prev2End = now()->subMonths(2)->endOfMonth();

        return [
            'actual' => Tab::make('MES ACTUAL')
                ->modifyQueryUsing(fn(Builder $q) => $applyCounts($q, $currStart, $currEnd)),

            'mes_pasado' => Tab::make('MES PASADO')
                ->modifyQueryUsing(fn(Builder $q) => $applyCounts($q, $prevStart, $prevEnd)),

            'hace_2_meses' => Tab::make('HACE 2 MESES')
                ->modifyQueryUsing(fn(Builder $q) => $applyCounts($q, $prev2Start, $prev2End)),
        ];
    }
}
