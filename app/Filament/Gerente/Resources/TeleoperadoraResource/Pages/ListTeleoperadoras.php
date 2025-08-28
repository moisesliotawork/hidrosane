<?php

// App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages\ListTeleoperadoras.php
namespace App\Filament\Gerente\Resources\TeleoperadoraResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\Gerente\Resources\TeleoperadoraResource;
use App\Models\Note;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTeleoperadoras extends ListRecords
{
    protected static string $resource = TeleoperadoraResource::class;

    public function getTabs(): array
    {
        // helper: aplica selects calculados por rango
        $applyCounts = function (Builder $q, Carbon $start, Carbon $end): void {
            $q->addSelect([
                // CONFIRMADAS
                'confirmadas_count' => Note::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('notes.user_id', 'users.id')
                    ->whereBetween('notes.created_at', [$start, $end])
                    ->where('notes.estado_terminal', EstadoTerminal::CONFIRMADO->value),
                // VENTAS
                'vendidas_count' => Note::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('notes.user_id', 'users.id')
                    ->whereBetween('notes.created_at', [$start, $end])
                    ->where('notes.estado_terminal', EstadoTerminal::VENTA->value),
            ]);
        };

        $currStart = now()->startOfMonth();
        $currEnd   = now()->endOfMonth();

        $prevStart = now()->subMonth()->startOfMonth();
        $prevEnd   = now()->subMonth()->endOfMonth();

        $prev2Start = now()->subMonths(2)->startOfMonth();
        $prev2End   = now()->subMonths(2)->endOfMonth();

        return [
            'actual' => Tab::make('MES ACTUAL')
                ->modifyQueryUsing(fn (Builder $q) => $applyCounts($q, $currStart, $currEnd)),

            'mes_pasado' => Tab::make('MES PASADO')
                ->modifyQueryUsing(fn (Builder $q) => $applyCounts($q, $prevStart, $prevEnd)),

            'hace_2_meses' => Tab::make('HACE 2 MESES')
                ->modifyQueryUsing(fn (Builder $q) => $applyCounts($q, $prev2Start, $prev2End)),
        ];
    }
}
