<?php

namespace App\Filament\HeadOfRoom\Resources\RetenResource\Pages;

use App\Filament\HeadOfRoom\Resources\RetenResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRetens extends ListRecords
{
    protected static string $resource = RetenResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todas';
    }

    public function getTabs(): array
    {
        $baseQuery = fn (): Builder => RetenResource::getEloquentQuery();

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return [
            'todas' => Tab::make('TODAS')
                ->icon('heroicon-o-queue-list')
                ->badge($baseQuery()->count())
                ->badgeColor('gray'),

            'hoy' => Tab::make('HOY')
                ->icon('heroicon-o-calendar-days')
                ->badge($baseQuery()->whereDate('created_at', $today)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', $today)),

            'ayer' => Tab::make('AYER')
                ->icon('heroicon-o-calendar')
                ->badge($baseQuery()->whereDate('created_at', $yesterday)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', $yesterday)),

            'anteriores' => Tab::make('ANTERIORES')
                ->icon('heroicon-o-archive-box')
                ->badge($baseQuery()->whereDate('created_at', '<', $yesterday)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', '<', $yesterday)),
        ];
    }
}
