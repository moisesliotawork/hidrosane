<?php

namespace App\Filament\SuperAdmin\Resources\RetenLogsResource\Pages;

use App\Filament\SuperAdmin\Resources\RetenLogsResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRetenLogs extends ListRecords
{
    protected static string $resource = RetenLogsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('TODAS'),

            'hoy' => Tab::make('HOY')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today())),

            'ayer' => Tab::make('AYER')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today()->subDay())),

            'anteriores' => Tab::make('ANTERIORES')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', '<', today()->subDay())),
        ];
    }
}
