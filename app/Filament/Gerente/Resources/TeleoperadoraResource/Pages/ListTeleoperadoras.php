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
            // Aseguramos users.* disponible aunque vengan joins previos
            $query->select('users.*');

            // CONFIRMADAS
            $query->selectSub(function ($q) use ($start, $end) {
                $q->from('notes')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('notes.user_id', 'users.id')
                    ->where('notes.estado_terminal', EstadoTerminal::CONFIRMADO->value)
                    ->whereBetween('notes.created_at', [$start, $end]);
            }, 'confirmadas_count');

            // VENTAS
            $query->selectSub(function ($q) use ($start, $end) {
                $q->from('notes')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('notes.user_id', 'users.id')
                    ->where('notes.estado_terminal', EstadoTerminal::VENTA->value)
                    ->whereBetween('notes.created_at', [$start, $end]);
            }, 'vendidas_count');

            return $query;
        };

        $currStart = now()->startOfMonth();
        $currEnd = now()->endOfMonth();
        $prevStart = now()->clone()->subMonth()->startOfMonth();
        $prevEnd = now()->clone()->subMonth()->endOfMonth();
        $prev2Start = now()->clone()->subMonths(2)->startOfMonth();
        $prev2End = now()->clone()->subMonths(2)->endOfMonth();

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
