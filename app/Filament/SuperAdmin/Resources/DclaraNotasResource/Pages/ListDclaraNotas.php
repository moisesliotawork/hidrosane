<?php

namespace App\Filament\SuperAdmin\Resources\DclaraNotasResource\Pages;

use App\Filament\SuperAdmin\Resources\DclaraNotasResource;
use App\Models\Note;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDclaraNotas extends ListRecords
{
    protected static string $resource = DclaraNotasResource::class;

    public ?string $tableSortColumn = 'fecha_declaracion';

    public ?string $tableSortDirection = 'desc';

    public function getTitle(): string
    {
        return 'DclaraNOTAS';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $baseQuery = fn(): Builder => Note::query()->whereNotNull('fecha_declaracion');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return [
            'todas' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet')
                ->badge($baseQuery()->count())
                ->badgeColor('gray'),

            'hoy' => Tab::make('Hoy')
                ->icon('heroicon-o-calendar-days')
                ->badge($baseQuery()->whereDate('fecha_declaracion', $today)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('fecha_declaracion', $today)),

            'ayer' => Tab::make('Ayer')
                ->icon('heroicon-o-calendar')
                ->badge($baseQuery()->whereDate('fecha_declaracion', $yesterday)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('fecha_declaracion', $yesterday)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todas';
    }
}
