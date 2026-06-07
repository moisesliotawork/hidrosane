<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Pages;

use App\Filament\SuperAdmin\Resources\DuplicadosResource;
use App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets\DuplicadosStatsWidget;
use App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets\FusionadosWidget;
use Filament\Resources\Pages\ListRecords;

class ListDuplicados extends ListRecords
{
    protected static string $resource = DuplicadosResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DuplicadosStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            FusionadosWidget::class,
        ];
    }
}
