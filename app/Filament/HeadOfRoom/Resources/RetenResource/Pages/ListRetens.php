<?php

namespace App\Filament\HeadOfRoom\Resources\RetenResource\Pages;

use App\Filament\HeadOfRoom\Resources\RetenResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRetens extends ListRecords
{
    protected static string $resource = RetenResource::class;

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
