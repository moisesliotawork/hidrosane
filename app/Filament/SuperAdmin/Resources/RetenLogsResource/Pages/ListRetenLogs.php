<?php

namespace App\Filament\SuperAdmin\Resources\RetenLogsResource\Pages;

use App\Filament\SuperAdmin\Resources\RetenLogsResource;
use App\Models\Note;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRetenLogs extends ListRecords
{
    protected static string $resource = RetenLogsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'hoy';
    }

    public function getTabs(): array
    {
        $baseQuery = fn (): Builder => RetenLogsResource::getEloquentQuery();
        $dateColumn = Note::RETEN_TAB_DATE_COLUMN;
        $dates = Note::retenTabDates();

        return [
            'todas' => Tab::make('TODAS')
                ->icon('heroicon-o-queue-list')
                ->badge($baseQuery()->count())
                ->badgeColor('gray'),

            'hoy' => Tab::make('HOY')
                ->icon('heroicon-o-calendar-days')
                ->badge($baseQuery()->whereDate($dateColumn, $dates['today'])->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate($dateColumn, $dates['today'])),

            'ayer' => Tab::make('AYER')
                ->icon('heroicon-o-calendar')
                ->badge($baseQuery()->whereDate($dateColumn, $dates['yesterday'])->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate($dateColumn, $dates['yesterday'])),

            'anteriores' => Tab::make('ANTERIORES')
                ->icon('heroicon-o-archive-box')
                ->badge($baseQuery()->whereDate($dateColumn, '<', $dates['yesterday'])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate($dateColumn, '<', $dates['yesterday'])),
        ];
    }
}
