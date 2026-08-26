<?php

namespace App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\HeadOfRoom\Pages\UnlockTeleoperadoras;
use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource;
use App\Filament\HeadOfRoom\Widgets\DailyProductionTablesWidget;
use App\Support\HeadOfRoom\TeleoperadorasAccess;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ListTeleoperadoras extends ListRecords
{
    protected static string $resource = TeleoperadoraResource::class;

    public function mount(): void
    {
        if (! TeleoperadorasAccess::isUnlocked()) {
            $this->redirect(UnlockTeleoperadoras::getUrl());

            return;
        }
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getFooterWidgets(): array
    {
        return [
            DailyProductionTablesWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }

    public function getTabs(): array
    {
        $tz = config('app.timezone', 'UTC');

        $thisStart = Carbon::now($tz)->startOfMonth()->startOfDay()->utc()->toDateTimeString();
        $thisEnd   = Carbon::now($tz)->endOfMonth()->endOfDay()->utc()->toDateTimeString();

        $prevStart = Carbon::now($tz)->subMonth()->startOfMonth()->startOfDay()->utc()->toDateTimeString();
        $prevEnd   = Carbon::now($tz)->subMonth()->endOfMonth()->endOfDay()->utc()->toDateTimeString();

        return [
            'this' => Tab::make('Mes actual')
                ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                    'notes as confirmadas_count' => fn ($q) => $q
                        ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)
                        ->whereBetween('fecha_declaracion', [$thisStart, $thisEnd]),
                    'notes as vendidas_count' => fn ($q) => $q
                        ->where('estado_terminal', EstadoTerminal::VENTA->value)
                        ->whereBetween('fecha_declaracion', [$thisStart, $thisEnd]),
                    'notes as aproduccion_count' => fn ($q) => $q
                        ->whereBetween('created_at', [$thisStart, $thisEnd]),
                ])),

            'prev' => Tab::make('Mes anterior')
                ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                    'notes as confirmadas_count' => fn ($q) => $q
                        ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)
                        ->whereBetween('fecha_declaracion', [$prevStart, $prevEnd]),
                    'notes as vendidas_count' => fn ($q) => $q
                        ->where('estado_terminal', EstadoTerminal::VENTA->value)
                        ->whereBetween('fecha_declaracion', [$prevStart, $prevEnd]),
                    'notes as aproduccion_count' => fn ($q) => $q
                        ->whereBetween('created_at', [$prevStart, $prevEnd]),
                ])),
        ];
    }
}
