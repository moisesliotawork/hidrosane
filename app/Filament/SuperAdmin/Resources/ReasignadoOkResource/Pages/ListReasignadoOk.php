<?php

namespace App\Filament\SuperAdmin\Resources\ReasignadoOkResource\Pages;

use App\Filament\SuperAdmin\Resources\ReasignadoOkResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReasignadoOk extends ListRecords
{
    protected static string $resource = ReasignadoOkResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('TODOS'),

            'hoy' => Tab::make('HOY')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('reassigned_at', today())),

            'ayer' => Tab::make('AYER')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('reassigned_at', today()->subDay())),

            'anteriores' => Tab::make('ANTERIORES')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('reassigned_at', '<', today()->subDay())),
        ];
    }
}
